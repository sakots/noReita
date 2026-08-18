import chokidar from "chokidar";
import * as sass from "sass";
import {
  readdir,
  writeFile,
  unlink,
} from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const ROOT_DIR = process.cwd();
const BUILD_ONCE = process.argv.includes("--once");

const IGNORE_DIRS = new Set([
  "node_modules",
  ".git",
  ".zed",
  ".idea",
  ".vscode",
]);

function isIgnored(filePath) {
  const relative = path.relative(ROOT_DIR, filePath);
  const parts = relative.split(path.sep);

  return parts.some(part => IGNORE_DIRS.has(part));
}

function isScss(filePath) {
  return filePath.endsWith(".scss");
}

// _foo.scss は partial 扱い。
// 単体のCSSは生成しない。
function isEntryScss(filePath) {
  return (
    isScss(filePath) &&
    !path.basename(filePath).startsWith("_") &&
    !isIgnored(filePath)
  );
}

async function findScssFiles(dir) {
  const entries = await readdir(dir, {
    withFileTypes: true,
  });

  const files = [];

  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name);

    if (entry.isDirectory()) {
      if (IGNORE_DIRS.has(entry.name)) {
        continue;
      }

      files.push(...await findScssFiles(fullPath));
      continue;
    }

    if (
      entry.isFile() &&
      isEntryScss(fullPath)
    ) {
      files.push(fullPath);
    }
  }

  return files;
}

async function compileOne(input, style, suffix) {
  const parsed = path.parse(input);

  const cssPath = path.join(
    parsed.dir,
    `${parsed.name}${suffix}.css`
  );

  const mapPath = `${cssPath}.map`;

  const result = await sass.compileAsync(input, {
    style,
    sourceMap: true,
    sourceMapIncludeSources: true,
  });

  const sourceMap = {
    ...result.sourceMap,
    // ローカルの絶対パスを配布物へ残さず、CIでも同じ内容を生成する。
    sources: result.sourceMap.sources.map(source => {
      try {
        return path.relative(parsed.dir, fileURLToPath(source)).split(path.sep).join("/");
      } catch {
        return source;
      }
    }),
    file: path.basename(cssPath),
  };

  const css =
    `${result.css}\n/*# sourceMappingURL=${path.basename(mapPath)} */\n`;

  await writeFile(cssPath, css);

  await writeFile(
    mapPath,
    JSON.stringify(sourceMap)
  );

  console.log(
    `✓ ${path.relative(ROOT_DIR, cssPath)}`
  );
}

async function compileFile(input) {
  await Promise.all([
    compileOne(
      input,
      "expanded",
      ""
    ),
    compileOne(
      input,
      "compressed",
      ".min"
    ),
  ]);
}

async function compileAll() {
  const entries = await findScssFiles(ROOT_DIR);
  let failures = 0;

  console.log(
    `\nSass: ${entries.length} entr${entries.length === 1 ? "y" : "ies"} found`
  );

  for (const entry of entries) {
    try {
      await compileFile(entry);
    } catch (error) {
      failures++;
      console.error(
        `\n✗ ${path.relative(ROOT_DIR, entry)}`
      );

      console.error(error instanceof Error ? error.message : String(error));
    }
  }

  return failures;
}

async function removeGenerated(input) {
  if (!isEntryScss(input)) {
    return;
  }

  const parsed = path.parse(input);

  const outputs = [
    path.join(
      parsed.dir,
      `${parsed.name}.css`
    ),
    path.join(
      parsed.dir,
      `${parsed.name}.css.map`
    ),
    path.join(
      parsed.dir,
      `${parsed.name}.min.css`
    ),
    path.join(
      parsed.dir,
      `${parsed.name}.min.css.map`
    ),
  ];

  for (const file of outputs) {
    try {
      await unlink(file);

      console.log(
        `− ${path.relative(ROOT_DIR, file)}`
      );
    } catch {
      // 無ければ何もしない
    }
  }
}

console.log(`${BUILD_ONCE ? "Building" : "Watching"} project: ${ROOT_DIR}`);
console.log("Target: **/*.scss");

const initialFailures = await compileAll();

if (BUILD_ONCE) {
  if (initialFailures > 0) {
    console.error(`\nSass build failed: ${initialFailures} entry(s)`);
    process.exitCode = 1;
  } else {
    console.log("\nSass build completed successfully");
  }
} else {
  let timer;

  function scheduleCompile() {
    clearTimeout(timer);

    timer = setTimeout(() => {
      compileAll();
    }, 100);
  }

  const watcher = chokidar.watch(ROOT_DIR, {
    ignoreInitial: true,

    ignored: filePath => {
      return isIgnored(filePath);
    },
  });

  watcher.on("add", file => {
    if (!isScss(file)) {
      return;
    }

    scheduleCompile();
  });

  watcher.on("change", file => {
    if (!isScss(file)) {
      return;
    }

    scheduleCompile();
  });

  watcher.on("unlink", async file => {
    if (!isScss(file)) {
      return;
    }

    await removeGenerated(file);
    scheduleCompile();
  });
}
