import {chromium} from 'playwright';
import {createProxy} from './proxy.mjs';
import {browserCheck} from './check.mjs';
const token=process.env.MONITOR_API_TOKEN;
const base=new URL(process.env.MONITOR_PANEL_URL??'');
if(base.protocol!=='https:' || base.username || base.password || base.search || base.hash || !/^[a-f0-9]{64}$/.test(token??''))throw Error('HTTPS panel URL and MONITOR_API_TOKEN required');
if(!base.pathname.endsWith('/'))base.pathname+='/';
async function api(action,body={}) {
 const url=new URL('browser-api.php',base);url.searchParams.set('action',action);
 const r=await fetch(url,{method:'POST',redirect:'error',signal:AbortSignal.timeout(20000),headers:{'Content-Type':'application/json','X-WebKontrol-Token':token},body:JSON.stringify(body)});
 if(!r.ok)throw Error('Panel API HTTP '+r.status+' ('+action+')');return r.json();
}
const proxy=await createProxy();let browser;let checked=0;
try {
 const env=Object.fromEntries(['PATH','HOME','TMPDIR','LANG','DISPLAY'].filter(k=>process.env[k]).map(k=>[k,process.env[k]]));
 browser=await chromium.launch({headless:true,env,proxy:{server:proxy.url,bypass:'<-loopback>'},args:['--disable-quic','--force-webrtc-ip-handling-policy=disable_non_proxied_udp']});
 const until=Date.now()+8*60*1000;
 while(Date.now()<until) {
  const {jobs}=await api('claim');if(!Array.isArray(jobs))throw Error('Invalid jobs response');if(!jobs.length)break;
  let cursor=0;
  await Promise.all(Array.from({length:Math.min(3,jobs.length)},async()=>{
   while(cursor<jobs.length){const job=jobs[cursor++];const result=await browserCheck(browser,job);await api('result',result);checked++;}
  }));
 }
 console.log('Completed browser checks: '+checked);
}finally{await browser?.close();await proxy.close();}
