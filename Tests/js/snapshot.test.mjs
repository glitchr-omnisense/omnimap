// assets/snapshot.js on node's test runner, over the few DOM calls it makes (no browser, no library).
import { test } from 'node:test';
import assert from 'node:assert/strict';

class FakeImage {
  static made = [];

  constructor() {
    this.listeners = {};
    this.complete = false;
    this.naturalWidth = 0;
    FakeImage.made.push(this);
  }

  addEventListener(type, listener) {
    this.listeners[type] = listener;
  }
}

function element(tagName, attributes = {}) {
  return {
    tagName,
    id: attributes.id || '',
    style: {},
    dataset: {},
    children: [],
    attributes: { ...attributes },
    clientWidth: 1000,
    clientHeight: 600,
    getAttribute(name) {
      return name in this.attributes ? this.attributes[name] : null;
    },
    removeAttribute(name) {
      if (name === 'data-background-image') delete this.dataset.backgroundImage;
      delete this.attributes[name];
    },
    append(child) {
      this.children.push(child);
    },
    querySelectorAll(selector) {
      if (selector === ':scope > span') return this.children;
      if (selector === '[data-background-image]') return this.children.filter((child) => child.dataset.backgroundImage !== undefined);
      return [];
    },
  };
}

globalThis.window = {};
globalThis.document = { createElement: (tagName) => element(tagName.toUpperCase()) };
globalThis.Image = FakeImage;

const { layout } = await import('../../assets/snapshot.js');

function frame(attributes) {
  return element('DIV', {
    id: 'map_snapshot',
    'data-src': '/omnimap/%7Bsignature%7D/%7Bid%7D.webp',
    'data-signature': 'abc',
    'data-tilesize': '512',
    'data-xtiles': '2',
    'data-ytiles': '1',
    width: '1000',
    height: '500',
    ...attributes,
  });
}

test('the tiles of a picture, each loaded from its address', () => {
  FakeImage.made = [];
  const picture = frame({ 'data-missing': '' });
  layout(picture);

  assert.equal(picture.children.length, 2);
  assert.deepEqual(FakeImage.made.map((image) => image.src), ['/omnimap/abc/0.webp', '/omnimap/abc/1.webp']);
  FakeImage.made[0].listeners.load();
  assert.equal(picture.children[0].style.backgroundImage, "url('/omnimap/abc/0.webp')");
  assert.equal(picture.children[0].style.opacity, '1');
});

test('data-missing: shown in place of a tile that does not load', () => {
  FakeImage.made = [];
  const picture = frame({ 'data-missing': '/images/no-image.png' });
  layout(picture);

  assert.equal(typeof FakeImage.made[1].listeners.error, 'function', 'a tile that fails is looked after');
  FakeImage.made[1].listeners.error();
  assert.equal(picture.children[1].style.backgroundImage, "url('/images/no-image.png')");
  assert.equal(picture.children[1].style.opacity, '1');
});

test('data-missing: shown over a frame with no picture kept', () => {
  const empty = frame({ 'data-tilesize': '', 'data-xtiles': '0', 'data-ytiles': '0', width: '0', height: '0', 'data-missing': '/images/no-image.png' });
  layout(empty);

  assert.equal(empty.children.length, 0, 'no tile');
  assert.equal(empty.style.backgroundImage, "url('/images/no-image.png')");
  assert.equal(empty.style.backgroundSize, 'cover');
});

test('no data-missing: a frame with no picture stays empty, a failed tile transparent', () => {
  FakeImage.made = [];
  const empty = frame({ 'data-xtiles': '0', 'data-ytiles': '0', 'data-missing': '' });
  layout(empty);
  assert.equal(empty.style.backgroundImage, undefined);

  const picture = frame({});
  layout(picture);
  FakeImage.made[0].listeners.error?.();
  assert.equal(picture.children[0].style.backgroundImage, undefined);
  assert.equal(picture.children[0].style.opacity, '0');
});
