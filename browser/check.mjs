export async function browserCheck(browser,job,{contentTimeout=20000}={}) {
 const url=new URL(job.url);
 if(!['http:','https:'].includes(url.protocol) || url.username || url.password || !['','80','443'].includes(url.port))throw Error('Invalid target URL');
 const start=Date.now();let result;
 for(let attempt=0;attempt<2;attempt++) {
  const context=await browser.newContext({viewport:{width:1000,height:650},ignoreHTTPSErrors:false,acceptDownloads:false,serviceWorkers:'block'});
  let page,stage='navigation';
  result={id:job.id,job:job.job,state:'down',status:0,latency:0,error:null};
  try {
   await context.route('**/*',route=>/^https?:/.test(route.request().url())?route.continue():route.abort());
   page=await context.newPage();
   page.on('response',response=>{if(response.request().isNavigationRequest() && response.frame()===page.mainFrame())result.status=response.status();});
   const response=await page.goto(job.url,{waitUntil:'commit',timeout:20000});
   result.status=response?.status()??0;
   stage='content';
   if(result.status>=200 && result.status<400) {
    await page.waitForFunction(()=>{
     const body=document.body;if(!body)return false;
     const box=body.getBoundingClientRect();if(!box.width || !box.height || getComputedStyle(body).visibility==='hidden')return false;
     return Boolean(body.innerText.trim() || Array.from(body.querySelectorAll('img,svg,canvas,video,iframe')).some(el=>{const r=el.getBoundingClientRect();return r.width>0 && r.height>0 && getComputedStyle(el).visibility!=='hidden';}));
    },null,{timeout:contentTimeout,polling:200});
    await page.waitForTimeout(1200);
    if(result.status>=200 && result.status<400)result.state='up';
    else result.error='Tarayıcı HTTP '+result.status+' yanıtı aldı.';
   }else result.error='Tarayıcı HTTP '+result.status+' yanıtı aldı.';
   result.latency=Date.now()-start;
   if(job.screenshot) {
    try {const image=await page.screenshot({type:'jpeg',quality:65,fullPage:false,timeout:5000});if(image.length>2000000)throw Error('Resim çok büyük');result.screenshot=image.toString('base64');}
    catch{result.screenshot_error='Tarayıcı ekran görüntüsü kaydedilemedi.';}
   }
  }catch(e){
   result.error=stage==='content' && e.name==='TimeoutError'?'HTTP '+result.status+' yanıtı alındı ancak '+Math.round(contentTimeout/1000)+' saniye içinde görünür içerik oluşmadı.':String(e.message).slice(0,1800);
   result.latency=Date.now()-start;
   if(job.screenshot && !result.screenshot)result.screenshot_error='Sayfa açılamadığı için yeni tarayıcı ekran görüntüsü alınamadı.';
  }
  finally{await context.close();}
  if(result.state==='up' || result.status>=400)break;
 }
 return result;
}
