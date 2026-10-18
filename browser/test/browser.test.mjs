import test from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import net from 'node:net';
import {chromium} from 'playwright';
import {isPublic,publicLookup,createProxy} from '../proxy.mjs';
import {browserCheck} from '../check.mjs';
test('Public network policy rejects local, mapped and reserved networks',async()=>{
 for(const ip of ['127.0.0.1','10.0.0.1','169.254.169.254','192.168.1.1','100.64.0.1','224.0.0.1','::1','::ffff:127.0.0.1','fc00::1','fe80::1','2001:db8::1'])assert.equal(isPublic(ip),false,ip);
 for(const ip of ['1.1.1.1','8.8.8.8','2606:4700:4700::1111'])assert.equal(isPublic(ip),true,ip);
 await assert.rejects(publicLookup('127.0.0.1'));
});
test('Proxy blocks direct private-address tunneling',async()=>{
 const proxy=await createProxy();
 try {
  const u=new URL(proxy.url);const reply=await new Promise((resolve,reject)=>{const s=net.connect(Number(u.port),'127.0.0.1',()=>s.write('CONNECT 127.0.0.1:443 HTTP/1.1\r\nHost: 127.0.0.1\r\n\r\n'));s.on('data',d=>{resolve(d.toString());s.destroy();});s.on('error',reject);});assert.match(reply,/502/);
 }finally{await proxy.close();}
});
test('Actual Chromium navigation, JPEG capture, HTTP errors and blank pages',{timeout:60000},async()=>{
 // The injected resolver is only a test fixture; production always uses publicLookup.
 const server=http.createServer((req,res)=>{res.setHeader('Content-Type','text/html');if(req.url==='/error'){res.statusCode=503;res.end('<h1>Unavailable</h1>');}else if(req.url==='/blank')res.end('<html><body></body></html>');else res.end('<html><body><h1>Working site</h1></body></html>');});
 await new Promise(r=>server.listen(80,'127.0.0.1',r));
 const proxy=await createProxy({resolve:async()=>['127.0.0.1']});let browser;
 try {
  browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{}),proxy:{server:proxy.url,bypass:'<-loopback>'},args:['--disable-quic']});
  const good=await browserCheck(browser,{id:1,job:'a'.repeat(48),url:'http://fixture.example/',screenshot:true});
  assert.equal(good.state,'up');assert.equal(good.status,200);assert.equal(good.error,null);assert.equal(Buffer.from(good.screenshot,'base64')[0],255);
  const bad=await browserCheck(browser,{id:2,job:'b'.repeat(48),url:'http://fixture.example/error',screenshot:false});assert.equal(bad.state,'down');assert.equal(bad.status,503);
  const blank=await browserCheck(browser,{id:3,job:'c'.repeat(48),url:'http://fixture.example/blank',screenshot:false});assert.equal(blank.state,'down');
  await assert.rejects(browserCheck(browser,{url:'file:///etc/passwd'}));
 }finally{await browser?.close();await proxy.close();await new Promise(r=>server.close(r));}
});
