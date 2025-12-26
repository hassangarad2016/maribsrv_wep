const fs = require("fs");
const puppeteer = require("puppeteer-extra");
const stealthPlugin = require("puppeteer-extra-plugin-stealth");

puppeteer.use(stealthPlugin());

const targetUrl = process.argv[2];
if (!targetUrl || !/^https?:\/\//i.test(targetUrl)) {
  process.stderr.write(
    JSON.stringify({ ok: false, error: "invalid_url" }, null, 2)
  );
  process.exit(1);
}

const resolveExecutablePath = () => {
  if (process.env.SHEIN_BROWSER_EXECUTABLE) {
    return process.env.SHEIN_BROWSER_EXECUTABLE;
  }

  const candidates = [
    "C:\\\\Program Files\\\\Microsoft\\\\Edge\\\\Application\\\\msedge.exe",
    "C:\\\\Program Files (x86)\\\\Microsoft\\\\Edge\\\\Application\\\\msedge.exe",
  ];
  for (const candidate of candidates) {
    if (fs.existsSync(candidate)) {
      return candidate;
    }
  }
  return null;
};

const extractInPage = () => {
  function meta(selector) {
    const node = document.querySelector(selector);
    return node ? node.content : null;
  }

  function absUrl(url) {
    if (!url) return null;
    if (url.startsWith("//")) return `${location.protocol}${url}`;
    if (url.startsWith("/")) return `${location.origin}${url}`;
    return url;
  }

  function goodImage(url) {
    if (!url) return false;
    const value = url.toLowerCase();
    if (!/^https?:/.test(value)) return false;
    if (!/\.(jpg|jpeg|png|webp)(\?|$)/.test(value)) return false;
    return !/(sprite|logo|icon|placeholder|avatar|favicon|brand)/.test(value);
  }

  function safeParse(text) {
    try {
      return JSON.parse(text);
    } catch (error) {
      return null;
    }
  }

  function walkJson(node, visitor) {
    if (!node) return;
    if (Array.isArray(node)) {
      node.forEach((child) => walkJson(child, visitor));
      return;
    }
    if (typeof node === "object") {
      visitor(node);
      Object.keys(node).forEach((key) => walkJson(node[key], visitor));
    }
  }

  let title = meta('meta[property="og:title"]') || document.title || null;
  let description =
    meta('meta[property="og:description"]') ||
    meta('meta[name="description"]') ||
    null;
  let price =
    meta('meta[property="product:price:amount"]') ||
    meta('meta[property="og:price:amount"]') ||
    null;
  let currency =
    meta('meta[property="product:price:currency"]') ||
    meta('meta[property="og:price:currency"]') ||
    null;

  const images = new Set();
  const ogImage = absUrl(meta('meta[property="og:image"]'));
  if (goodImage(ogImage)) images.add(ogImage);

  document.querySelectorAll("img").forEach((img) => {
    [
      "src",
      "data-src",
      "data-original",
      "data-lazy",
      "data-bg",
      "data-image",
      "data-zoom-image",
      "data-bigimg",
      "data-big",
    ].forEach((attr) => {
      const value = absUrl(img.getAttribute(attr));
      if (goodImage(value)) images.add(value);
    });

    const srcset = img.getAttribute("srcset");
    if (srcset) {
      srcset.split(",").forEach((part) => {
        const value = absUrl(part.trim().split(" ")[0]);
        if (goodImage(value)) images.add(value);
      });
    }
  });

  const properties = [];
  let variants = [];
  let stockTotal = null;

  function pushProperty(name, items) {
    if (!name || !items || !items.length) return;
    const index = properties.findIndex(
      (property) =>
        property.name.toLowerCase() === String(name).toLowerCase()
    );
    if (index >= 0) {
      const seen = new Set(
        properties[index].items.map((item) => item.id || item.name)
      );
      items.forEach((item) => {
        const key = item.id || item.name;
        if (key && !seen.has(key)) properties[index].items.push(item);
      });
    } else {
      properties.push({ name: String(name), items });
    }
  }

  document
    .querySelectorAll('script[type="application/ld+json"]')
    .forEach((script) => {
      const data = safeParse(script.textContent.trim());
      if (!data) return;
      walkJson(data, (node) => {
        const type = (node["@type"] || "").toString().toLowerCase();
        if (type !== "product") return;
        if (!title && node.name) title = node.name;
        if (!description && node.description) description = node.description;
        if (node.offers) {
          let offer = node.offers;
          if (Array.isArray(offer)) offer = offer[0] || {};
          price =
            price ||
            (offer.price != null ? String(offer.price) : null);
          currency = currency || offer.priceCurrency || null;
        }
        const image = node.image;
        if (image) {
          (Array.isArray(image) ? image : [image]).forEach((url) => {
            const resolved = absUrl(url);
            if (goodImage(resolved)) images.add(resolved);
          });
        }
      });
    });

  const skuHints =
    /(sku[_-]?list|sku[_-]?info|skuPropertyList|skuMap|product_id|goods_id|stock|attribute|attrValList|saleAttr)/i;
  let bigJson = null;

  Array.from(document.querySelectorAll("script")).some((script) => {
    const text = script.textContent || "";
    if (skuHints.test(text) && text.replace(/\s+/g, "").length > 5000) {
      const start = text.indexOf("{");
      const end = text.lastIndexOf("}");
      if (start >= 0 && end > start) {
        const slice = text.slice(start, end + 1);
        const parsed = safeParse(slice);
        if (parsed) {
          bigJson = parsed;
          return true;
        }
      }
    }
    return false;
  });

  function extractFromCommonStructures(root) {
    if (!root) return;

    if (!currency) {
      const candidates = [];
      walkJson(root, (node) => {
        if (typeof node.currency === "string") candidates.push(node.currency);
        if (typeof node.priceCurrency === "string")
          candidates.push(node.priceCurrency);
        if (node.money && node.money.currency)
          candidates.push(node.money.currency);
      });
      if (candidates.length) currency = candidates[0];
    }

    const propBuckets = [];
    walkJson(root, (node) => {
      const lists = [
        node.skuPropertyList,
        node.saleAttr,
        node.saleAttrs,
        node.attributes,
        node.props,
        node.prop_list,
      ].filter(Boolean);

      lists.forEach((list) => {
        if (!Array.isArray(list)) return;
        list.forEach((prop) => {
          const name = prop.name || prop.propName || prop.attrName || prop.title;
          const itemsRaw =
            prop.values ||
            prop.attrValList ||
            prop.valueList ||
            prop.items ||
            prop.attr_value_list;
          let items = [];
          if (Array.isArray(itemsRaw)) {
            items = itemsRaw
              .map((value) => {
                const id =
                  value.id ||
                  value.vid ||
                  value.valueId ||
                  value.attrId ||
                  value.skuId ||
                  null;
                const itemName =
                  value.name ||
                  value.value ||
                  value.attrValue ||
                  value.text ||
                  value.title ||
                  String(id || "").trim();
                const available =
                  value.available ?? value.enable ?? value.status ?? true;
                const color = value.color || value.rgb || value.hex || null;
                const image = absUrl(value.imgUrl || value.image || value.img);
                return {
                  id,
                  name: itemName,
                  available: Boolean(available),
                  color: color || null,
                  image: image || null,
                };
              })
              .filter((item) => item && item.name);
          }
          if (name && items.length) {
            propBuckets.push({ name, items });
          }
        });
      });
    });
    propBuckets.forEach((prop) => pushProperty(prop.name, prop.items));

    let localVariants = [];
    walkJson(root, (node) => {
      const skuList = node.skuList || node.sku_list || node.skus || node.sku;
      if (Array.isArray(skuList)) {
        skuList.forEach((sku) => {
          const skuId = sku.skuId || sku.id || sku.sku || sku.goods_sku || null;
          const skuPrice =
            sku.price != null
              ? String(sku.price)
              : sku.salePrice != null
              ? String(sku.salePrice)
              : sku.activityPrice != null
              ? String(sku.activityPrice)
              : null;
          const skuStock =
            sku.stock != null
              ? Number(sku.stock)
              : sku.inventory != null
              ? Number(sku.inventory)
              : null;
          const props = {};
          const pairs =
            sku.saleAttrs ||
            sku.attributes ||
            sku.specs ||
            sku.props ||
            sku.properties;
          if (Array.isArray(pairs)) {
            pairs.forEach((pair) => {
              const key =
                pair.name || pair.propName || pair.attrName || pair.key;
              const value =
                pair.value ||
                pair.attrValue ||
                pair.val ||
                pair.text ||
                pair.propValue;
              if (key && value) props[key] = value;
            });
          }
          localVariants.push({
            skuId,
            price: skuPrice,
            currency: currency || null,
            stock: skuStock,
            props,
          });
        });
      }
      if (node.stockTotal != null && stockTotal == null)
        stockTotal = Number(node.stockTotal);
      if (node.totalStock != null && stockTotal == null)
        stockTotal = Number(node.totalStock);
    });
    if (localVariants.length) variants = localVariants;
  }

  if (bigJson) extractFromCommonStructures(bigJson);

  const guesses = [
    window.__NUXT__,
    window.__NEXT_DATA__,
    window.g_config,
    window.g_page_config,
    window.__INITIAL_STATE__,
  ];
  guesses.forEach((guess) => extractFromCommonStructures(guess));

  return {
    href: location.href,
    title: title || null,
    description: description || null,
    price: price || null,
    currency: currency || null,
    images: Array.from(images).slice(0, 30),
    properties,
    variants,
    stockTotal,
  };
};

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const run = async () => {
  const executablePath = resolveExecutablePath();
  if (!executablePath) {
    process.stderr.write(
      JSON.stringify({ ok: false, error: "browser_not_found" }, null, 2)
    );
    process.exit(1);
  }

  const headlessEnv = String(process.env.SHEIN_HEADLESS || "").toLowerCase();
  const headless = headlessEnv === "0" || headlessEnv === "false" ? false : "new";
  const userDataDir = process.env.SHEIN_BROWSER_PROFILE;

  const launchOptions = {
    headless,
    executablePath,
    args: [
      "--disable-blink-features=AutomationControlled",
      "--no-sandbox",
      "--disable-setuid-sandbox",
    ],
  };

  if (userDataDir) {
    launchOptions.userDataDir = userDataDir;
  }

  const browser = await puppeteer.launch(launchOptions);

  const page = await browser.newPage();
  await page.setUserAgent(
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
  );
  await page.setViewport({ width: 1280, height: 720 });
  await page.setExtraHTTPHeaders({
    "accept-language": "ar,en-US;q=0.8,en;q=0.7",
  });
  page.setDefaultTimeout(45000);

  await page.goto(targetUrl, { waitUntil: "domcontentloaded" });
  await sleep(2000);
  const currentUrl = page.url();
  if (/\/risk\/challenge/i.test(currentUrl)) {
    await browser.close();
    process.stderr.write(
      JSON.stringify(
        { ok: false, error: "risk_challenge", url: currentUrl },
        null,
        2
      )
    );
    process.exit(2);
  }
  await page.evaluate(() => {
    window.scrollTo(0, document.body.scrollHeight);
  });
  await sleep(1200);
  await page.waitForNetworkIdle({ idleTime: 1000, timeout: 8000 }).catch(() => {});

  const data = await page.evaluate(extractInPage);
  await browser.close();

  process.stdout.write(JSON.stringify({ ok: true, data }, null, 2));
};

run().catch((error) => {
  process.stderr.write(
    JSON.stringify({ ok: false, error: error.message || String(error) }, null, 2)
  );
  process.exit(1);
});
