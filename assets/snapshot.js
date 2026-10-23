/**
 * The picture of a map kept in omnimap's cache, laid out in the page:
 * Omnimap\MapHtml::snapshot() prints a frame ([data-omnimap="snapshot"])
 * with the picture's size and its grid of tiles; this lays the tiles out to
 * cover the frame (object-fit: cover, centred), and loads each tile only
 * once the frame is shown and the tile is on screen. No library.
 *
 * The algorithm is glitchr/ux-google's tilemap.js, without jQuery: the grid
 * is fitted by the capture's real size (the last row and column are padding)
 * and grown by COPYRIGHT_MARGIN at the bottom.
 *
 *   import { initSnapshots, autoInit } from './snapshot.js';
 *   autoInit();     // on load, resize, and transparent.js's page swaps
 */

const SELECTOR = '[data-omnimap="snapshot"]';

/** The share of the grid pushed below the frame's bottom edge (a capture's bottom strip). */
export const COPYRIGHT_MARGIN = 0.04;

function visible(element) {
  if (!element.isConnected) return false;
  const style = window.getComputedStyle(element);
  if (style.display === 'none' || style.visibility === 'hidden') return false;
  return !(element.offsetParent === null && style.position !== 'fixed');
}

function fit(containerWidth, containerHeight, width, height) {
  const ratio = width / height;
  let w;
  let h;
  if (ratio < containerWidth / containerHeight) {
    w = containerWidth;
    h = w / ratio;
  } else {
    h = containerHeight;
    w = h * ratio;
  }
  return { width: w, height: h, left: (containerWidth - w) / 2, top: (containerHeight - h) / 2 };
}

function lazyLoad(frame) {
  const tiles = frame.querySelectorAll('[data-background-image]');
  if (!('IntersectionObserver' in window)) {
    tiles.forEach((tile) => reveal(tile));
    return;
  }
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      observer.unobserve(entry.target);
      reveal(entry.target);
    });
  });
  tiles.forEach((tile) => observer.observe(tile));
}

function reveal(tile) {
  const src = tile.dataset.backgroundImage;
  tile.removeAttribute('data-background-image');
  if (!src) return;
  const image = new Image();
  image.fetchPriority = 'high';
  image.decoding = 'async';
  image.src = src;
  const show = () => {
    tile.style.backgroundImage = `url('${image.src}')`;
    tile.style.opacity = '1';
  };
  if (image.complete && image.naturalWidth > 0) {
    tile.style.transition = 'none';
    show();
    requestAnimationFrame(() => { tile.style.transition = ''; });
  } else {
    image.addEventListener('load', show, { once: true });
  }
}

/** Lays one frame's tiles out (creating them the first time). */
export function layout(frame) {
  if (frame.tagName !== 'DIV') throw new Error('omnimap: a snapshot frame is a div.');
  Object.assign(frame.style, { objectFit: 'cover', position: 'relative', top: '50%', left: '50%', transform: 'translate(-50%, -50%)', width: '100%', height: '100%' });

  const src = decodeURI(frame.getAttribute('data-src') || '');
  const signature = frame.getAttribute('data-signature') || '';
  const xtiles = parseInt(frame.getAttribute('data-xtiles'), 10) || 0;
  const ytiles = parseInt(frame.getAttribute('data-ytiles'), 10) || 0;
  const width = parseFloat(frame.getAttribute('width')) || 0;
  const height = parseFloat(frame.getAttribute('height')) || 0;
  const tilesize = parseInt(frame.getAttribute('data-tilesize'), 10) || Math.max(width, height);
  if (!xtiles || !ytiles || !tilesize) return;

  const gridWidth = xtiles * tilesize;
  const gridHeight = ytiles * tilesize;
  const captureWidth = width > 0 && height > 0 ? width : gridWidth;
  const captureHeight = width > 0 && height > 0 ? height : gridHeight;
  const cover = fit(frame.clientWidth, frame.clientHeight, captureWidth, captureHeight);
  const grid = {
    left: cover.left,
    top: cover.top,
    width: cover.width * (gridWidth / captureWidth),
    height: cover.height * (gridHeight / captureHeight),
  };
  const grownWidth = grid.width * (1 + COPYRIGHT_MARGIN);
  grid.left -= (grownWidth - grid.width) / 2;
  grid.width = grownWidth;
  grid.height *= 1 + COPYRIGHT_MARGIN;

  const spans = frame.querySelectorAll(':scope > span');
  const size = Math.max(Math.floor(grid.width / xtiles), Math.floor(grid.height / ytiles));
  for (let y = 0; y < ytiles; y++) {
    for (let x = 0; x < xtiles; x++) {
      const index = y * xtiles + x;
      let tile = spans[index];
      if (!tile) {
        tile = document.createElement('span');
        tile.id = `${frame.id}_${index}`;
        tile.dataset.backgroundImage = src.replaceAll('{signature}', signature).replaceAll('{id}', String(index));
        tile.style.opacity = '0';
        tile.style.transition = 'opacity 0.25s ease';
        frame.append(tile);
      }
      const w = x === xtiles - 1 ? grid.width - size * x : size;
      const h = y === ytiles - 1 ? grid.height - size * y : size;
      Object.assign(tile.style, {
        position: 'absolute',
        left: `${grid.left + x * size}px`,
        top: `${grid.top + y * size}px`,
        width: `${w + 0.1}px`,
        height: `${h + 0.1}px`,
        backgroundSize: `${w + 0.1}px ${h + 0.1}px`,
      });
    }
  }
  lazyLoad(frame);
}

let observer = null;

/** Lays out every frame under root once it is near the screen and shown. */
export function initSnapshots(root = document) {
  const frames = root.querySelectorAll(SELECTOR);
  if (!('IntersectionObserver' in window)) {
    frames.forEach((frame) => {
      if (frame.dataset.omnimapInitialized === '1') return;
      frame.dataset.omnimapInitialized = '1';
      layout(frame);
    });
    return;
  }
  observer = observer || new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      const frame = entry.target;
      if (!entry.isIntersecting || !visible(frame) || frame.dataset.omnimapInitialized === '1') return;
      frame.dataset.omnimapInitialized = '1';
      observer.unobserve(frame);
      layout(frame);
    });
  }, { rootMargin: '200px' });
  frames.forEach((frame) => {
    if (frame.dataset.omnimapInitialized !== '1') observer.observe(frame);
  });
}

/** Lays the frames already shown out again (a resize), then looks for new ones. */
export function relayout(root = document) {
  root.querySelectorAll(SELECTOR).forEach((frame) => {
    if (frame.dataset.omnimapInitialized === '1' && visible(frame)) layout(frame);
  });
  initSnapshots(root);
}

/** On load, on resize, and after transparent.js swaps a page in. */
export function autoInit() {
  const init = () => initSnapshots();
  if (document.readyState === 'complete') init();
  else window.addEventListener('load', init);
  window.addEventListener('resize', () => relayout());
  window.addEventListener('transparent:ready', init);
  window.addEventListener('transparent:postactive', init);
}
