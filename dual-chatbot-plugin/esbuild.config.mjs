import { build } from 'esbuild';
import { existsSync, mkdirSync } from 'node:fs';
import { join, dirname } from 'node:path';

const watch = process.argv.includes('--watch');

async function ensureDir(path) {
  const dir = dirname(path);
  if (!existsSync(dir)) mkdirSync(dir, { recursive: true });
}

async function buildJS() {
  const outfile = 'assets/js/chatbot.min.js';
  await ensureDir(outfile);
  await build({
    entryPoints: ['assets/js/src/index.js'],
    outfile,
    bundle: true,
    minify: true,
    sourcemap: true,
    target: ['es2019'],
    format: 'iife',
    logLevel: 'info',
  // watch: watch ? { onRebuild(error) { if (error) console.error('rebuild failed:', error); else console.log('rebuild succeeded'); } } : false,
  });
}

async function buildCSS() {
  // Simple CSS minify via esbuild
  const infile = 'assets/css/chatbot.css';
  const outfile = 'assets/css/chatbot.min.css';
  await ensureDir(outfile);
  await build({
    entryPoints: [infile],
    outfile,
    bundle: false,
    minify: true,
    loader: { '.css': 'css' },
    logLevel: 'info',
  // watch: false,
  });
}

await buildJS();
await buildCSS();

