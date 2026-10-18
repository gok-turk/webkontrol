export async function browserCheck(browser,job) {
 const url=new URL(job.url);
 if(!['http:','https:'].includes(url.protocol) || url.username || url.password || !['','80','443'].includes(url.port))throw Error('Invalid target URL');
 const start=Date.now();let result;
 for(let attempt=0;attempt<2;attempt++) {
  const context=await browser.newContext({viewport:{width:1000,height:650},ignoreHTTPSErrors:false,acceptDownloads:false,serviceWorkers:'block'});
  let page;
  result={id:job.id,job:job.job,state:'down',status:0,latency:0,error:null};
  try {
   await context.route('**/*',route=>/^https?:/.test(route.request().url())?route.continue():route.abort());
   page=await context.newPage();
   const response=await page.goto(job.url,{waitUntil:'domcontentloaded',timeout:20000});
   result.status=response?.status()??0;
   await page.waitForTimeout(1200);
   const visible=await page.evaluate(()=>Boolean(document.body && (document.body.innerText.trim() || document.body.querySelector('img,svg,canvas,video,iframe'))));
   if(result.status>=200 && result.status<400 && visible)result.state='up';
   else result.error=visible?'Tarayıcı HTTP '+result.status+' yanıtı aldı.':'Tarayıcıda sayfa içeriği görüntülenemedi.';
   result.latency=Date.now()-start;
   if(job.screenshot) {
    try {const image=await page.screenshot({type:'jpeg',quality:65,fullPage:false,timeout:5000});if(image.length>2000000)throw Error('Resim çok büyük');result.screenshot=image.toString('base64');}
    catch{result.screenshot_error='Tarayıcı ekran görüntüsü kaydedilemedi.';}
   }
  }catch(e){result.error=String(e.message).slice(0,1800);result.latency=Date.now()-start;}
  finally{await context.close();}
  if(result.state==='up' || result.status>=400)break;
 }
 return result;
}
