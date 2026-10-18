import {chromium} from 'playwright';
import {appendFileSync} from 'node:fs';
import {createProxy} from './proxy.mjs';
import {browserCheck} from './check.mjs';
import {createApi} from './api.mjs';
const api=createApi({base:process.env.MONITOR_PANEL_URL??'',token:process.env.MONITOR_API_TOKEN});
let proxy,browser,checked=0,failed=0;
function summary(message){console.log(message);if(process.env.GITHUB_STEP_SUMMARY)appendFileSync(process.env.GITHUB_STEP_SUMMARY,message+'\n\n');}
try {
 proxy=await createProxy();
 const env=Object.fromEntries(['PATH','HOME','TMPDIR','LANG','DISPLAY'].filter(k=>process.env[k]).map(k=>[k,process.env[k]]));
 browser=await chromium.launch({headless:true,env,proxy:{server:proxy.url,bypass:'<-loopback>'},args:['--disable-quic','--force-webrtc-ip-handling-policy=disable_non_proxied_udp']});
 const until=Date.now()+8*60*1000;
 while(Date.now()<until) {
  const {jobs}=await api.call('claim');if(!Array.isArray(jobs))throw Error('Invalid jobs response');if(!jobs.length)break;
  let cursor=0;
  await Promise.all(Array.from({length:Math.min(3,jobs.length)},async()=>{
   while(cursor<jobs.length){
    const job=jobs[cursor++];
    try {
     const result=await browserCheck(browser,job);await api.call('result',result);checked++;
     console.log('Saved browser result for site #'+job.id);
    }catch(e){failed++;console.error('Site #'+job.id+': '+e.message);}
   }
  }));
  // Stop claiming more work if the panel is unreachable, after finishing this batch.
  if(failed)break;
 }
 summary('Completed browser checks: '+checked+'; unsaved/unfinished: '+failed+'.');
 if(failed)throw Error('Bazı sonuçlar kaydedilemedi. Önceki site durumları korundu; işler kilit süresi dolunca sonraki çalışmada tekrar alınacak.');
}catch(e){summary('Browser monitoring stopped: '+e.message);process.exitCode=1;}
finally{await browser?.close();await proxy?.close();api.close();}
