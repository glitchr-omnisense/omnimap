/**
 * omnimap in the browser: draws the description a provider's renderer gave
 * (Omnimap\MapRendererInterface::describe(), whose shape schema/map-description.schema.json
 * gives) with that provider's driver -
 * framework-free, an ES module: a Twig page, a Laravel view and a React
 * component use it alike.
 *
 *   import { register, mountAll } from './omnimap.js';
 *   import google from './google.js';            // omnimap/google
 *   import leaflet from './leaflet.js';          // omnimap/openstreetmap
 *   register('google', google);
 *   register('openstreetmap', leaflet);
 *   mountAll(document, { consent: (feature, load) => Consent.use(feature, {}, load) });
 *
 * A live map loads a third party's script (Google's, Leaflet's) and its
 * tiles: `consent` is the gate it goes through (omnibase/consent's
 * Consent.use(), say). Without one, it loads at once.
 */

const drivers = new Map();

/** A driver: { draw(element, description, options) -> Promise<map> }; options carry loadScript() and loadStyle(). */
export function register(provider, driver) {
  drivers.set(provider, driver);
}

export function draw(element, description, options = {}) {
  const driver = drivers.get(description.provider);
  if (!driver) {
    return Promise.reject(new Error(`omnimap: no driver registered for "${description.provider}".`));
  }
  const gate = options.consent || ((feature, load) => load());

  return new Promise((resolve, reject) => {
    gate(description.consent || 'MAP', () => {
      Promise.resolve(driver.draw(element, description, { ...options, loadScript, loadStyle })).then((map) => {
        element.dispatchEvent(new CustomEvent('omnimap:ready', { bubbles: true, detail: { map, description } }));
        resolve(map);
      }, reject);
    });
  });
}

/** Draws every [data-omnimap="map"] element under root, once. */
export function mountAll(root = document, options = {}) {
  const maps = [];
  root.querySelectorAll('[data-omnimap="map"]').forEach((element) => {
    if (element.dataset.omnimapMounted === '1') return;
    element.dataset.omnimapMounted = '1';
    let description;
    try {
      description = JSON.parse(element.dataset.omnimapDescription || '{}');
    } catch (error) {
      console.error('omnimap: unreadable description', error);
      return;
    }
    maps.push(draw(element, description, options).catch((error) => {
      console.error(error);
      element.style.display = 'none';
    }));
  });

  return Promise.all(maps);
}

const loading = new Map();

/** Adds a script once; resolves when it has run. */
export function loadScript(src, attributes = {}) {
  if (!loading.has(src)) {
    loading.set(src, new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = src;
      script.async = true;
      Object.entries(attributes).forEach(([name, value]) => { if (value) script.setAttribute(name, value); });
      const nonce = document.querySelector('script[nonce]');
      if (nonce && nonce.nonce) script.nonce = nonce.nonce;
      script.onload = resolve;
      script.onerror = () => reject(new Error(`omnimap: ${src} could not load.`));
      document.head.append(script);
    }));
  }

  return loading.get(src);
}

/** Adds a stylesheet once. */
export function loadStyle(href, attributes = {}) {
  if (document.querySelector(`link[rel="stylesheet"][href="${CSS.escape(href)}"]`)) return;
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = href;
  Object.entries(attributes).forEach(([name, value]) => { if (value) link.setAttribute(name, value); });
  document.head.append(link);
}
