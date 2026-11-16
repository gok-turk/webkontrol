import {isIP} from 'node:net';

export function expiration(record) {
 const dates=(record.events??[]).filter(e=>e.eventAction==='expiration' && typeof e.eventDate==='string')
  .map(e=>Date.parse(e.eventDate)).filter(t=>Number.isFinite(t) && t>0);
 return dates.length?Math.floor(Math.min(...dates)/1000):0;
}

// The caller supplies a request context using the public-address-only proxy.
export async function lookupDomain(url,request,{now=Date.now}={}) {
 let host=new URL(url).hostname.replace(/^\[|\]$/g,'').replace(/\.$/,'').toLowerCase();
 if(isIP(host) || !host.includes('.'))return {expires:0,error:'IP adresleri için domain bitiş tarihi bulunmaz.'};
 const deadline=now()+12000;
 try {
  for(let level=0;level<8 && host.includes('.');level++,host=host.slice(host.indexOf('.')+1)) {
   let endpoint='https://rdap.org/domain/'+encodeURIComponent(host);
   let notFound=false;
   for(let hop=0;hop<5;hop++) {
    const parsed=new URL(endpoint);
    if(parsed.protocol!=='https:' || parsed.username || parsed.password || (parsed.port && parsed.port!=='443'))throw Error('Geçersiz RDAP yönlendirmesi.');
    const timeout=deadline-now();if(timeout<=0)throw Error('Domain sorgusu zaman aşımına uğradı.');
    const response=await request.get(endpoint,{timeout,maxRedirects:0,headers:{Accept:'application/rdap+json, application/json'}});
    try {
     const status=response.status();
     if(status>=300 && status<400 && response.headers().location){endpoint=new URL(response.headers().location,endpoint).href;continue;}
     if(status===404){notFound=true;break;}
     if(status!==200)throw Error('Domain kayıt servisi HTTP '+status+' yanıtı verdi.');
     const bytes=await response.body();if(bytes.length>1048576)throw Error('Domain kayıt yanıtı çok büyük.');
     const record=JSON.parse(bytes.toString());
     if(record.objectClassName!=='domain' || record.ldhName?.toLowerCase()!==host)throw Error('Domain kayıt servisi geçersiz yanıt verdi.');
     const expires=expiration(record);
     return {expires,error:expires?null:'Kayıt servisi domain bitiş tarihini paylaşmıyor.'};
    }finally{await response.dispose();}
   }
   if(!notFound)throw Error('Domain kayıt servisi yönlendirme sınırı aşıldı.');
  }
  throw Error('Bu domain için RDAP kaydı bulunamadı.');
 }catch{return {expires:0,error:'Domain bitiş tarihi alınamadı; kayıt servisi erişimi veya RDAP desteği kontrol edilmeli.'};}
}
