// Synthetic fixtures only: no employee data or external image requests.
const fs=require('node:fs'); const vm=require('node:vm'); const ts=require('typescript'); const {JSDOM}=require('jsdom');
const dom=new JSDOM('<div id="veStaffList"></div>'); const {window}=dom; const root=window.document.querySelector('#veStaffList');
for(let i=0;i<37;i++) {
  const id=String(i).padStart(2,'0');
  const department=i===0?'Small Alpha':i<3?'Small Beta':i<33?'Large Sales':'Small Gamma';
  const photo='data:image/svg+xml,'+encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" fill="silver"/></svg>');
  root.insertAdjacentHTML('beforeend',`<div data-dept="${department}" class="employee-tile ${i===36?'ve-hidden':''}"><div class="employee-tile-img"><img src="${photo}"></div><div class="employee-details"><h3 class="employee-name">Employee ${id}</h3><span class="employee-title">Service Advisor</span><span class="employee-location">Example Office</span></div><div class="ve-contact-wrap"><div class="ve-detail-row"><span class="ve-detail-label">Email</span><span class="ve-detail-value">person${id}@example.com</span></div><div class="ve-detail-row"><span class="ve-detail-label">Direct</span><span class="ve-detail-value">605-555-0100</span></div></div><span class="years-service">17 years of service</span><span class="ve-staff-tags">Confidential tag</span><button>View More Info</button></div>`);
}
const context={exports:{},window,document:window.document,MutationObserver:window.MutationObserver};
vm.runInNewContext(ts.transpileModule(fs.readFileSync('src/runtime/directory.ts','utf8'),{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2018}}).outputText,context);
const runtime=context.exports.createDirectoryRuntime(); fs.mkdirSync('/tmp/ve-print-check',{recursive:true});
for(const orientation of ['portrait','landscape']) {
  const doc=window.document.implementation.createHTMLDocument(''); runtime.buildPrintDocument(root,doc,orientation);
  fs.writeFileSync('/tmp/ve-print-check/'+orientation+'.html','<!doctype html>'+doc.documentElement.outerHTML);
}
window.close();
