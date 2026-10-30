// ve-select2.js includes pinned upstream vendor builds in a private module scope.
// Bundle the site's reviewed behavior; never execute scripts returned by the API.
const fs = require('fs');
const path = require('path');
const project = path.resolve(__dirname, '..');
const source = path.resolve(project, '../../assets/themes/vern-eide-staff/inc/assets/js');
const modules = ['ve-select2.js', 've-lazy-load.js', 'listing.js'].map(name => {
  const text = fs.readFileSync(path.join(source, name), 'utf8');
  const marker = '  var namespace = windowObject.VEStaffDirectory || {};';
  const position = text.indexOf(marker);
  const start = position < 0 ? -1 : position + marker.length;
  const moduleEnd = text.indexOf('  // END DIRECTORY MODULE');
  const end = moduleEnd >= 0 ? moduleEnd : text.indexOf('  function initializeDocument()');
  if (start < 0 || end < start) throw new Error(`Runtime structure changed: ${name}`);
  return '(function () {\n' + text.slice(start, end).replace(/  windowObject\.VEStaffDirectory = namespace;\n/g, '') + '\n}());';
});
const target = path.join(project, 'src/runtime');
fs.mkdirSync(target, { recursive: true });
fs.writeFileSync(path.join(target, 'directory.ts'), '// @ts-nocheck\n// Generated from WordPress sources by scripts/prepare-runtime.cjs.\nexport function createDirectoryRuntime() {\nconst windowObject = window;\nconst namespace = { integration: "sharepoint" };\n' + modules.join('\n') + '\nreturn namespace;\n}\n');
