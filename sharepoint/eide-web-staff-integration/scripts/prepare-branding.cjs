// Package the supplied PNG unchanged so App Catalog does not depend on an external icon URL.
const https = require('https');
const fs = require('fs');
const path = require('path');

const source = 'https://vern-eide.s3.amazonaws.com/cdn/logos/VE_Secondary_Black_1x1_250px.png';
const destination = path.resolve(__dirname, '../sharepoint/assets/ve-staff-icon-v2.png');
const request = https.get(source, response => {
  if (response.statusCode !== 200) {
    response.resume();
    request.destroy(new Error('Logo download returned HTTP ' + response.statusCode));
    return;
  }
  const chunks = [];
  let size = 0;
  response.on('data', chunk => {
    size += chunk.length;
    if (size > 1024 * 1024) request.destroy(new Error('Logo exceeds 1 MB.'));
    else chunks.push(chunk);
  });
  response.on('error', fail);
  response.on('end', () => {
    if (request.destroyed && !response.complete) return;
    const png = Buffer.concat(chunks);
    if (png.length < 24 || png.subarray(0, 8).toString('hex') !== '89504e470d0a1a0a' ||
        png.readUInt32BE(16) !== 250 || png.readUInt32BE(20) !== 250) {
      fail(new Error('Expected the supplied 250 by 250 PNG logo.'));
      return;
    }
    fs.mkdirSync(path.dirname(destination), { recursive: true });
    fs.writeFileSync(destination, png);
    console.log('Packaged Vern Eide app icon.');
  });
});
function fail(error) {
  console.error(error.message);
  process.exitCode = 1;
}
request.setTimeout(30000, () => request.destroy(new Error('Logo download timed out.')));
request.on('error', fail);
