import https from 'node:https';
const transientCodes=new Set(['ECONNRESET','ECONNREFUSED','ETIMEDOUT','EAI_AGAIN','ENETUNREACH','EHOSTUNREACH','EPIPE']);
const transientStatuses=new Set([429,500,502,503,504]);
export class PanelError extends Error {
 constructor(message,{retryable=false,status=0,code=''}={}){super(message);this.retryable=retryable;this.status=status;this.code=code;}
}
export function sendJson(url,{headers,body,agent,timeout=40000,connectTimeout=20000}) {
 return new Promise((resolve,reject)=>{
  let connectedTimer;
  const req=https.request(url,{method:'POST',headers,agent,family:4},res=>{
   let bytes='';res.setEncoding('utf8');
   res.on('data',chunk=>{bytes+=chunk;if(bytes.length>65536)req.destroy(Object.assign(Error('Panel response too large'),{code:'EPROTO'}));});
   res.on('end',()=>resolve({status:res.statusCode,body:bytes}));res.on('error',reject);
  });
  const timer=setTimeout(()=>req.destroy(Object.assign(Error('Panel request timeout'),{code:'ETIMEDOUT'})),timeout);
  req.on('socket',socket=>{
   if(!socket.connecting)return;
   connectedTimer=setTimeout(()=>req.destroy(Object.assign(Error('Panel connection timeout'),{code:'ETIMEDOUT'})),connectTimeout);
   socket.once('secureConnect',()=>clearTimeout(connectedTimer));
  });
  req.on('error',reject);req.on('close',()=>{clearTimeout(timer);clearTimeout(connectedTimer);});
  req.end(body);
 });
}
export function createApi({base,token,send=sendJson,sleep=ms=>new Promise(r=>setTimeout(r,ms)),delay=1000}) {
 base=new URL(base);
 if(base.protocol!=='https:' || base.username || base.password || base.search || base.hash || !/^[a-f0-9]{64}$/.test(token??''))throw Error('HTTPS panel URL and MONITOR_API_TOKEN required');
 if(!base.pathname.endsWith('/'))base.pathname+='/';
 const agent=new https.Agent({keepAlive:true,maxSockets:1,maxFreeSockets:1});let tail=Promise.resolve(),circuitError=null;
 async function request(action,body) {
  const url=new URL('browser-api.php',base);url.searchParams.set('action',action);
  const payload=JSON.stringify(body);let last;
  for(let attempt=0;attempt<4;attempt++) {
   if(attempt)await sleep([0,1500,4000,8000][attempt]);
   try {
    const r=await send(url,{headers:{'Content-Type':'application/json','Content-Length':Buffer.byteLength(payload),'X-WebKontrol-Token':token},body:payload,agent});
    if(r.status<200 || r.status>=300)throw new PanelError('Panel API HTTP '+r.status+' ('+action+')',{retryable:transientStatuses.has(r.status),status:r.status});
    try{return JSON.parse(r.body);}catch{throw new PanelError('Panel API returned invalid JSON ('+action+')');}
   }catch(e) {
    last=e instanceof PanelError?e:new PanelError('GitHub → '+base.hostname+':443 bağlantısı başarısız ('+action+', '+(e.code??'NETWORK_ERROR')+'). Hosting erişimini kontrol edin.',{retryable:transientCodes.has(e.code),code:e.code});
    if(!last.retryable)throw last;
   }
  }
  circuitError=last;throw last;
 }
 return {
  call(action,body={}) {
   // Serialize uploads and reuse the TLS connection to avoid connection bursts.
   const current=tail.then(async()=>{if(circuitError)throw circuitError;await sleep(delay);return request(action,body);});
   tail=current.catch(()=>{});return current;
  },
  close(){agent.destroy();}
 };
}
