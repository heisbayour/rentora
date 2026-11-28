// Builds an upload-only-what-changed patch between two deploy packages.
// Usage: node scripts/make-patch.mjs <oldHtdocsDir> <newHtdocsDir> <outDir>
// <outDir> gets every new or changed file at its htdocs path, plus
// CHANGED_FILES.txt and REMOVED_FILES.txt (files to delete on the server).
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const [oldDir, newDir, outDir] = process.argv.slice(2);
if (!oldDir || !newDir || !outDir) {
    console.error('Usage: node scripts/make-patch.mjs <old> <new> <out>');
    process.exit(1);
}

function list(dir) {
    const files = new Map();
    const walk = (d) => {
        for (const e of fs.readdirSync(d, { withFileTypes: true })) {
            const p = path.join(d, e.name);
            if (e.isDirectory()) walk(p);
            else files.set(path.relative(dir, p).split(path.sep).join('/'), p);
        }
    };
    walk(dir);
    return files;
}

const hash = (p) => crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex');

const before = list(oldDir);
const after = list(newDir);
const changed = [];
const added = [];
const removed = [];

for (const [rel, p] of after) {
    if (!before.has(rel)) added.push(rel);
    else if (hash(before.get(rel)) !== hash(p)) changed.push(rel);
}
for (const rel of before.keys()) {
    if (!after.has(rel)) removed.push(rel);
}

fs.rmSync(outDir, { recursive: true, force: true });
for (const rel of [...changed, ...added]) {
    const target = path.join(outDir, rel);
    fs.mkdirSync(path.dirname(target), { recursive: true });
    fs.copyFileSync(after.get(rel), target);
}
const lines = (title, items) => `${title} (${items.length})\n${items.sort().map((i) => `  ${i}`).join('\n')}\n`;
fs.writeFileSync(path.join(outDir, '..', `${path.basename(outDir)}-CHANGED_FILES.txt`), lines('Changed', changed) + '\n' + lines('Added', added));
fs.writeFileSync(path.join(outDir, '..', `${path.basename(outDir)}-REMOVED_FILES.txt`), lines('Delete these on the server (no longer used)', removed));

console.log(`changed ${changed.length}, added ${added.length}, removed ${removed.length}`);
