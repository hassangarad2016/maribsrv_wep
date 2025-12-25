<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use App\Services\BootstrapTableService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Throwable;

class StaffController extends Controller {

    public function index() {
        ResponseService::noAnyPermissionThenRedirect(['staff-list', 'staff-create', 'staff-update', 'staff-delete']);
        $roles = Role::where('custom_role', 1)->get();
        $serviceCategories = Category::query()
            ->whereIn('id', [2, 8, 174, 175, 176, 114, 181, 180, 177])
            ->orderBy('name')
            ->get(['id', 'name']);
        return view('staff.index', compact('roles', 'serviceCategories'));
    }

    public function create() {
        ResponseService::noPermissionThenRedirect('staff-create');
        $roles = Role::where('custom_role', 1)->get();
        $serviceCategories = Category::query()
            ->whereIn('id', [2, 8, 174, 175, 176, 114, 181, 180, 177])
            ->orderBy('name')
            ->get(['id', 'name']);
        return view('staff.create', compact('roles', 'serviceCategories'));
    }

    public function store(Request $request) {
        ResponseService::noPermissionThenRedirect('staff-create');
        $validator = Validator::make($request->all(), [
            'name'     => 'required',
            'email'    => 'required|email|unique:users',
            'password' => 'required',
            'role'     => 'required',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password)
            ]);

            $user->syncRoles($request->role);
            $categoryIds = collect($request->input('category_ids', []))
                ->filter()
                ->map(static fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
            $user->managedCategories()->sync($categoryIds);
            DB::commit();
            ResponseService::successResponse('User created Successfully');
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "StaffController --> store");
            ResponseService::errorResponse();
        }
    }


    public function update(Request $request, $id) {
        ResponseService::noPermissionThenRedirect('staff-edit');
        $validator = Validator::make($request->all(), [
            'name'    => 'required',
            'email'   => 'required|email|unique:users,email,' . $id,
            'role_id' => 'required',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id'
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $user = User::withTrashed()->findOrFail($id);
            $user->update($request->only(['name', 'email']));

            $oldRole = $user->roles;
            if ($oldRole->isEmpty() || $oldRole[0]->id !== (int) $request->role_id) {
                $newRole = Role::findById($request->role_id);
                if (! $oldRole->isEmpty()) {
                    $user->removeRole($oldRole[0]);
                }
                $user->assignRole($newRole);
            }
            $categoryIds = collect($request->input('category_ids', []))
                ->filter()
                ->map(static fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
            $user->managedCategories()->sync($categoryIds);

            DB::commit();
            ResponseService::successResponse('User Update Successfully');
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "StaffController --> update");
            ResponseService::errorResponse();
        }
    }

    public function show(Request $request) {
        ResponseService::noPermissionThenRedirect('staff-list');
        $offset = $request->offset ?? 0;
        $limit = $request->limit ?? 10;
        $sort = $request->sort ?? 'id';
        $order = $request->order ?? 'DESC';

        $sql = User::withTrashed()
            ->with(['roles', 'managedServices:id', 'managedCategories:id'])
            ->orderBy($sort, $order)
            ->whereHas('roles', function ($q) {
            $q->where('custom_role', 1);
        });

        if (!empty($request->search)) {
            $sql->search($request->search);
        }
        $total = $sql->count();
        $sql->skip($offset)->take($limit);
        $result = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        foreach ($result as $key => $row) {
            $operate = '';
            if (Auth::user()->can('staff-update')) {
                $operate .= BootstrapTableService::editButton(route('staff.update', $row->id), true);
                $operate .= BootstrapTableService::editButton(route('staff.change-password', $row->id), true, '#resetPasswordModel', null, $row->id, 'bi bi-key');
            }

            if (Auth::user()->can('staff-delete')) {
                $operate .= BootstrapTableService::deleteButton(route('staff.destroy', $row->id));
            }

            $tempRow = $row->toArray();
            $tempRow['status'] = empty($row->deleted_at);
            $tempRow['managed_service_ids'] = $row->managedServices
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->values()
                ->all();
            $tempRow['managed_category_ids'] = $row->managedCategories
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->values()
                ->all();
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function destroy($id) {
        try {
            ResponseService::noPermissionThenSendJson('staff-delete');
            User::withTrashed()->findOrFail($id)->forceDelete();
            ResponseService::successResponse('User Delete Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "StaffController --> delete");
            ResponseService::errorResponse();
        }
    }


    public function changePassword(Request $request, $id) {
        ResponseService::noPermissionThenRedirect('staff-edit');
        $validator = Validator::make($request->all(), [
            'new_password'     => 'required|min:8',
            'confirm_password' => 'required|same:new_password'
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            User::findOrFail($id)->update(['password' => Hash::make($request->confirm_password)]);
            ResponseService::successResponse('Password Reset Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "StaffController -> changePassword");
            ResponseService::errorResponse();
        }

    }
}
