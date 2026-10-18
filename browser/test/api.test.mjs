import test from 'node:test';
import assert from 'node:assert/strict';
import https from 'node:https';
import {mkdtempSync,readFileSync,rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {execFileSync} from 'node:child_process';
import {createApi,sendJson} from '../api.mjs';
const config={base:'https://panel.example/public/',token:'a'.repeat(64),delay:0,sleep:async()=>{}};
test('Transient connection timeouts retry the same result without changing its nonce',async()=>{
 let calls=0;const bodies=[];
 const api=createApi({...config,send:async(url,options)=>{calls++;bodies.push(options.body);if(calls<3)throw Object.assign(Error('timeout'),{code:'ETIMEDOUT'});return {status:200,body:'{"ok":true}'};}});
 try{assert.deepEqual(await api.call('result',{id:1,job:'abc'}),{ok:true});assert.equal(calls,3);assert.equal(new Set(bodies).size,1);}finally{api.close();}
});
test('Permanent authentication errors are reported without retry',async()=>{
 let calls=0;const api=createApi({...config,send:async()=>{calls++;return {status:401,body:'{}'};}});
 try{await assert.rejects(api.call('claim'),/HTTP 401/);assert.equal(calls,1);}finally{api.close();}
});
test('Panel failures retry HTTP 503 and serialize API requests',async()=>{
 let inFlight=0,max=0,calls=0;
 const api=createApi({...config,send:async()=>{inFlight++;max=Math.max(max,inFlight);await new Promise(r=>setTimeout(r,5));inFlight--;calls++;return calls===1?{status:503,body:'{}'}:{status:200,body:'{"ok":true}'};}});
 try{await Promise.all([api.call('result',{id:1}),api.call('result',{id:2})]);assert.equal(calls,3);assert.equal(max,1);}finally{api.close();}
});
test('Unavailable panel opens circuit after bounded retries, preserving other pending jobs',async()=>{
 let calls=0;const api=createApi({...config,send:async()=>{calls++;throw Object.assign(Error('timeout'),{code:'ETIMEDOUT'});}});
 try{const results=await Promise.allSettled([api.call('result'),api.call('result'),api.call('result')]);assert(results.every(r=>r.status==='rejected'));assert.equal(calls,4);assert.match(results[0].reason.message,/panel.example:443/);assert(!results[0].reason.message.includes(config.token));}finally{api.close();}
});
test('HTTPS upload uses trusted TLS, IPv4 and rejects untrusted certificates',{timeout:20000},async()=>{
 const dir=mkdtempSync(join(tmpdir(),'webkontrol-tls-'));let server,agent;
 try {
  execFileSync('openssl',['req','-x509','-newkey','rsa:2048','-nodes','-keyout',join(dir,'key.pem'),'-out',join(dir,'cert.pem'),'-days','1','-subj','/CN=localhost','-addext','subjectAltName=IP:127.0.0.1'],{stdio:'ignore'});
  const cert=readFileSync(join(dir,'cert.pem'));
  let received;
  server=https.createServer({key:readFileSync(join(dir,'key.pem')),cert},(req,res)=>{let body='';req.on('data',d=>body+=d);req.on('end',()=>{received={method:req.method,token:req.headers['x-webkontrol-token'],body};res.setHeader('Content-Type','application/json');res.end('{"ok":true}');});});
  await new Promise(r=>server.listen(0,'127.0.0.1',r));const url=new URL('https://127.0.0.1:'+server.address().port+'/api');
  agent=new https.Agent({ca:cert,keepAlive:true,maxSockets:1});const options={headers:{'X-WebKontrol-Token':config.token},body:'{"id":1}',agent};
  const result=await sendJson(url,options);assert.equal(result.status,200);assert.deepEqual(received,{method:'POST',token:config.token,body:'{"id":1}'});
  await assert.rejects(sendJson(url,{...options,agent:new https.Agent()}),e=>['DEPTH_ZERO_SELF_SIGNED_CERT','UNABLE_TO_VERIFY_LEAF_SIGNATURE'].includes(e.code));
 }finally{agent?.destroy();if(server)await new Promise(r=>server.close(r));rmSync(dir,{recursive:true,force:true});}
});
