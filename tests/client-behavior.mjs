import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const applicationRoot = new URL('../noreita/', import.meta.url);

function element(tagName = 'div') {
  return {
    tagName: tagName.toUpperCase(),
    children: [],
    hidden: false,
    style: {},
    textContent: '',
    append(...children) {
      this.children.push(...children);
    },
    appendChild(child) {
      this.children.push(child);
      return child;
    },
    replaceChildren(...children) {
      this.children = children;
    },
  };
}

async function executeClientScript(filename, globals) {
  const source = await readFile(new URL(filename, applicationRoot), 'utf8');
  const context = vm.createContext(globals);
  vm.runInContext(source, context, { filename });
  await new Promise(setImmediate);
  return context;
}

async function postPreviewRendersSafeTextAndLinks() {
  const row = element('tr');
  row.hidden = true;
  const output = element('div');
  const comment = {
    value: '本文 <b>そのまま</b>\nhttps://example.com/card, https://example.com/card',
  };
  const listeners = new Map();
  const form = {
    querySelector(selector) {
      return {
        '[data-post-preview-row]': row,
        '[data-post-preview-content]': output,
        'textarea[name="com"]': comment,
      }[selector] ?? null;
    },
    querySelectorAll() {
      return [];
    },
    addEventListener(type, listener) {
      listeners.set(type, listener);
    },
  };
  let fetches = 0;
  const document = {
    querySelectorAll(selector) {
      return selector === 'form' ? [form] : [];
    },
    createElement: element,
  };

  await executeClientScript('post-preview.js', {
    document,
    URL,
    fetch() {
      fetches++;
      throw new Error('post preview must not fetch external pages');
    },
  });

  assert.equal(row.hidden, false);
  assert.equal(output.children.length, 4);
  assert.equal(output.children[0].textContent, '本文');
  assert.equal(output.children[1].textContent, comment.value);
  assert.equal(output.children[1].style.whiteSpace, 'pre-wrap');
  assert.equal(output.children[2].textContent, '外部リンク（投稿後にリンクカードを生成できる場合があります）');
  assert.equal(output.children[3].children.length, 1);
  const link = output.children[3].children[0].children[0];
  assert.equal(link.href, 'https://example.com/card');
  assert.equal(link.textContent, 'https://example.com/card');
  assert.equal(link.target, '_blank');
  assert.equal(link.rel, 'noopener noreferrer');
  assert.equal(fetches, 0);
  assert.equal(listeners.has('input'), true);
  assert.equal(listeners.has('change'), true);
}

async function tripPreviewPostsSecretsAndRendersResponse() {
  const endpoint = 'index.php?mode=trip_preview';
  const preview = element('output');
  preview.hidden = true;
  const token = { value: 'csrf-token' };
  const listeners = new Map();
  const input = {
    value: 'name#secret-key',
    parentElement: {
      querySelector(selector) {
        return selector === '[data-trip-preview]' ? preview : null;
      },
    },
    form: {
      querySelector(selector) {
        return selector === 'input[name="token"]' ? token : null;
      },
    },
    addEventListener(type, listener) {
      listeners.set(type, listener);
    },
  };
  const loader = { dataset: { endpoint }, src: 'https://board.example/trip-preview.js' };
  let sentRequest = null;
  const document = {
    currentScript: loader,
    scripts: [loader],
    querySelectorAll(selector) {
      return selector === '[data-trip-preview-input]' ? [input] : [];
    },
  };

  await executeClientScript('trip-preview.js', {
    AbortController,
    Date,
    URLSearchParams,
    document,
    window: { clearTimeout, setTimeout },
    async fetch(url, options) {
      sentRequest = { url, options };
      return {
        ok: true,
        status: 200,
        headers: { get: () => null },
        async json() {
          return { preview: '◆trip' };
        },
      };
    },
  });
  await new Promise(setImmediate);

  assert.ok(sentRequest);
  assert.equal(sentRequest.url, endpoint);
  assert.equal(sentRequest.url.includes('secret-key'), false);
  assert.equal(sentRequest.options.method, 'POST');
  assert.equal(sentRequest.options.credentials, 'same-origin');
  assert.equal(sentRequest.options.headers['Content-Type'], 'application/x-www-form-urlencoded');
  assert.equal(sentRequest.options.body.get('value'), input.value);
  assert.equal(sentRequest.options.body.get('token'), token.value);
  assert.equal(preview.textContent, 'トリップ: ◆trip');
  assert.equal(preview.hidden, false);
  assert.equal(listeners.has('input'), true);
}

const tests = [
  ['post preview renders safe text and links without external requests', postPreviewRendersSafeTextAndLinks],
  ['trip preview posts secrets and renders the server response', tripPreviewPostsSecretsAndRendersResponse],
];

let failed = 0;
for (const [name, test] of tests) {
  try {
    await test();
    console.log(`PASS: ${name}`);
  } catch (error) {
    failed++;
    console.error(`FAIL: ${name}`);
    console.error(error);
  }
}

console.log(`\nClient behavior tests: ${tests.length - failed} passed, ${failed} failed.`);
if (failed > 0) process.exitCode = 1;
