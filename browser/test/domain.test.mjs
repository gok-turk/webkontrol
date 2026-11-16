import test from 'node:test';
import assert from 'node:assert/strict';
import {lookupDomain,expiration} from '../domain.mjs';

function fixture(replies) {
 const calls=[];
 return {calls,async get(url,options){calls.push({url,options});const reply=replies.shift();if(reply instanceof Error)throw reply;assert.ok(reply,'unexpected request');return {status:()=>reply.status??200,headers:()=>reply.headers??{},body:async()=>Buffer.from(JSON.stringify(reply.body)),dispose:async()=>{}};}};
}
const record={objectClassName:'domain',ldhName:'example.com',events:[{eventAction:'registration',eventDate:'2020-01-01T00:00:00Z'},{eventAction:'expiration',eventDate:'2030-01-01T00:00:00Z'}]};
test('uses expiration event, not registration date',()=>{
 assert.equal(expiration(record),1893456000);
 assert.equal(expiration({events:[{eventAction:'expiration',eventDate:'invalid'}]}),0);
});
test('resolves subdomain through not-found and registry redirect',async()=>{
 const request=fixture([{status:404},{status:302,headers:{location:'https://registry.example/domain/example.com'}},{body:record}]);
 assert.deepEqual(await lookupDomain('https://www.example.com/path',request),{expires:1893456000,error:null});
 assert.equal(request.calls[1].url,'https://rdap.org/domain/example.com');
 assert.equal(request.calls[0].options.maxRedirects,0);
});
test('unknown date and registry failure do not produce fabricated dates',async()=>{
 for(const replies of [[{body:{...record,events:[]}}],[{status:429}],[Error('timeout')],[{body:{...record,ldhName:'other.com'}}]]){
  const result=await lookupDomain('https://example.com',fixture(replies));assert.equal(result.expires,0);assert.ok(result.error);
 }
});
test('rejects insecure registry redirects',async()=>{
 const request=fixture([{status:302,headers:{location:'http://registry.example/'}}]);
 assert.ok((await lookupDomain('https://example.com',request)).error);assert.equal(request.calls.length,1);
});
test('IP addresses skip registry requests',async()=>{
 const request=fixture([]);
 for(const url of ['http://127.0.0.1','https://[::1]'])assert.equal((await lookupDomain(url,request)).expires,0);
 assert.equal(request.calls.length,0);
});
