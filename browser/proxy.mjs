import http from 'node:http';
import net from 'node:net';
import {lookup} from 'node:dns/promises';
const blocked = new net.BlockList();
for (const [ip,bits] of [['0.0.0.0',8],['10.0.0.0',8],['100.64.0.0',10],['127.0.0.0',8],['169.254.0.0',16],['172.16.0.0',12],['192.0.0.0',24],['192.0.2.0',24],['192.168.0.0',16],['192.88.99.0',24],['198.18.0.0',15],['198.51.100.0',24],['203.0.113.0',24],['224.0.0.0',4],['240.0.0.0',4]]) blocked.addSubnet(ip,bits,'ipv4');
const globalV6=new net.BlockList(); globalV6.addSubnet('2000::',3,'ipv6');
blocked.addSubnet('2001:db8::',32,'ipv6');blocked.addSubnet('2001::',23,'ipv6');blocked.addSubnet('2002::',16,'ipv6');
export function isPublic(ip) {
 const family=net.isIP(ip);return family===4?!blocked.check(ip,'ipv4'):family===6 && globalV6.check(ip,'ipv6') && !blocked.check(ip,'ipv6');
}
export async function publicLookup(host) {
 const entries=net.isIP(host)?[{address:host}]:await lookup(host,{all:true});
 if(!entries.length || entries.some(e=>!isPublic(e.address)))throw Error('Private or reserved network address blocked');
 return [...new Set(entries.map(e=>e.address))].sort((a,b)=>Number(a.includes(':'))-Number(b.includes(':')));
}
async function connectPublic(host,port,resolve) {
 if(![80,443].includes(port))throw Error('Only HTTP/HTTPS ports supported');
 let last;
 for(const ip of await resolve(host)) {
  try{return await new Promise((res,rej)=>{const socket=net.connect({host:ip,port});socket.setTimeout(5000,()=>socket.destroy(Error('Connection timeout')));socket.once('error',rej);socket.once('connect',()=>{socket.removeListener('error',rej);socket.setTimeout(45000,()=>socket.destroy());res(socket);});});}catch(e){last=e;}
 }
 throw last??Error('No reachable address');
}
// Resolve, validate and pin the TCP socket, including all redirects and subresources.
export async function createProxy({resolve=publicLookup}={}) {
 const sockets=new Set();
 const server=http.createServer(async(req,res)=>{
  let upstream;
  try {
   const u=new URL(req.url);if(u.protocol!=='http:' || u.username || u.password)throw Error('Invalid HTTP URL');
   const socket=await connectPublic(u.hostname,Number(u.port||80),resolve);
   const headers={...req.headers,host:u.host};delete headers['proxy-authorization'];delete headers['proxy-connection'];
   const agent=new http.Agent({keepAlive:false});agent.createConnection=()=>socket;
   upstream=http.request({hostname:u.hostname,port:Number(u.port||80),method:req.method,path:u.pathname+u.search,headers,agent},r=>{res.writeHead(r.statusCode,r.headers);r.pipe(res);});
   upstream.on('error',()=>{if(!res.headersSent)res.writeHead(502);res.end('Upstream connection failed');});req.pipe(upstream);
   res.on('close',()=>upstream.destroy());
  }catch{res.writeHead(502);res.end('Upstream connection failed or private address blocked');}
 });
 server.on('connect',async(req,client,head)=>{
  let upstream;
  client.on('error',()=>upstream?.destroy());client.on('close',()=>upstream?.destroy());
  try {
   const u=new URL('http://'+req.url);if(u.username || u.password || u.pathname!=='/' || u.search || u.hash)throw Error('Invalid tunnel');
   upstream=await connectPublic(u.hostname.replace(/^\[|\]$/g,''),Number(u.port||80),resolve);
   if(client.destroyed){upstream.destroy();return;}
   upstream.on('error',()=>client.destroy());upstream.on('close',()=>client.destroy());
   client.write('HTTP/1.1 200 Connection Established\r\n\r\n');if(head.length)upstream.write(head);client.pipe(upstream);upstream.pipe(client);
  }catch{client.end('HTTP/1.1 502 Bad Gateway\r\nContent-Length: 0\r\n\r\n');}
 });
 server.on('connection',s=>{sockets.add(s);s.on('error',()=>{});s.on('close',()=>sockets.delete(s));});
 await new Promise(res=>server.listen(0,'127.0.0.1',res));
 return {url:'http://127.0.0.1:'+server.address().port,close:async()=>{for(const s of sockets)s.destroy();await new Promise(res=>server.close(res));}};
}
