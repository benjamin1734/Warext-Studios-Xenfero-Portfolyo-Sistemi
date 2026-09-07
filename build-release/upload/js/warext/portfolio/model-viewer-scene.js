(() => {
'use strict';
const V=window.WrxtPortfolioViewer;if(!V)return;
const {gl,MAX_JOINTS,mat4Identity,mat4Multiply,mat4Invert,transformPoint,quatSlerp}=V;
function parseGlb(buffer) {
  const dv=new DataView(buffer);
  if (dv.getUint32(0,true)!==0x46546c67 || dv.getUint32(4,true)!==2 || dv.getUint32(8,true)!==buffer.byteLength) throw new Error('GLB başlığı geçersiz.');
  let off=12,json=null,bin=null,index=0;
  while(off<buffer.byteLength){const len=dv.getUint32(off,true),type=dv.getUint32(off+4,true);off+=8;if(off+len>buffer.byteLength)throw new Error('GLB parçası bozuk.');const chunk=buffer.slice(off,off+len);off+=len;if(index===0&&type===0x4E4F534A)json=JSON.parse(new TextDecoder().decode(chunk).replace(/[\u0000\s]+$/g,''));else if(index===1&&type===0x004E4942)bin=chunk;else throw new Error('Beklenmeyen GLB parçası.');index++;}
  if(!json)throw new Error('GLB JSON bulunamadı.');return {json,bin:bin||new ArrayBuffer(0)};
}

const compInfo={5120:[Int8Array,1,gl.BYTE],5121:[Uint8Array,1,gl.UNSIGNED_BYTE],5122:[Int16Array,2,gl.SHORT],5123:[Uint16Array,2,gl.UNSIGNED_SHORT],5125:[Uint32Array,4,gl.UNSIGNED_INT],5126:[Float32Array,4,gl.FLOAT]};
const comps={SCALAR:1,VEC2:2,VEC3:3,VEC4:4,MAT2:4,MAT3:9,MAT4:16};

function accessorData(gltf,index,forceFloat=false){
  const a=gltf.json.accessors[index],v=gltf.json.bufferViews[a.bufferView],info=compInfo[a.componentType],count=a.count,n=comps[a.type],byteOffset=(v.byteOffset||0)+(a.byteOffset||0),stride=v.byteStride||info[1]*n;
  if(!v.byteStride){const C=forceFloat?Float32Array:info[0];if(forceFloat&&a.componentType!==5126){const src=new info[0](gltf.bin,byteOffset,count*n),out=new Float32Array(count*n);for(let i=0;i<out.length;i++)out[i]=normalizeComponent(src[i],a.componentType,a.normalized);return out;}return new C(gltf.bin,byteOffset,count*n);}
  const out=forceFloat?new Float32Array(count*n):new info[0](count*n),dv=new DataView(gltf.bin);
  for(let i=0;i<count;i++)for(let c=0;c<n;c++){const o=byteOffset+i*stride+c*info[1];let val;if(a.componentType===5120)val=dv.getInt8(o);else if(a.componentType===5121)val=dv.getUint8(o);else if(a.componentType===5122)val=dv.getInt16(o,true);else if(a.componentType===5123)val=dv.getUint16(o,true);else if(a.componentType===5125)val=dv.getUint32(o,true);else val=dv.getFloat32(o,true);out[i*n+c]=forceFloat?normalizeComponent(val,a.componentType,a.normalized):val;}
  return out;
}

function normalizeComponent(v,t,n){if(!n||t===5126)return v;if(t===5120)return Math.max(v/127,-1);if(t===5121)return v/255;if(t===5122)return Math.max(v/32767,-1);if(t===5123)return v/65535;if(t===5125)return v/4294967295;return v;}

function bufferAttribute(data,location,size,integer=false,normalized=false,type=gl.FLOAT){const b=gl.createBuffer();gl.bindBuffer(gl.ARRAY_BUFFER,b);gl.bufferData(gl.ARRAY_BUFFER,data,gl.STATIC_DRAW);gl.enableVertexAttribArray(location);if(integer)gl.vertexAttribIPointer(location,size,type,0,0);else gl.vertexAttribPointer(location,size,type,normalized,0,0);return b;}

async function buildScene(gltf){
  const json=gltf.json;
  const textures=[];
  for(const tex of (json.textures||[])){const img=json.images[tex.source],view=json.bufferViews[img.bufferView],bytes=new Uint8Array(gltf.bin,(view.byteOffset||0),view.byteLength),blob=new Blob([bytes],{type:img.mimeType}),bitmap=await createImageBitmap(blob);const t=gl.createTexture();gl.bindTexture(gl.TEXTURE_2D,t);gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL,false);gl.texImage2D(gl.TEXTURE_2D,0,gl.RGBA,gl.RGBA,gl.UNSIGNED_BYTE,bitmap);gl.generateMipmap(gl.TEXTURE_2D);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MIN_FILTER,gl.LINEAR_MIPMAP_LINEAR);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MAG_FILTER,gl.LINEAR);textures.push(t);bitmap.close();}
  const materials=(json.materials||[]).map(m=>{const p=m.pbrMetallicRoughness||{},f=p.baseColorFactor||[1,1,1,1];return {color:new Float32Array(f),texture:p.baseColorTexture?textures[p.baseColorTexture.index]:null,unlit:!!(m.extensions&&m.extensions.KHR_materials_unlit),doubleSided:!!m.doubleSided};});
  const primitives=[];
  (json.meshes||[]).forEach((mesh,meshIndex)=>mesh.primitives.forEach(pr=>{const vao=gl.createVertexArray();gl.bindVertexArray(vao);const posA=json.accessors[pr.attributes.POSITION],pos=accessorData(gltf,pr.attributes.POSITION,true);bufferAttribute(pos,0,3);if(pr.attributes.NORMAL!==undefined)bufferAttribute(accessorData(gltf,pr.attributes.NORMAL,true),1,3);else{gl.disableVertexAttribArray(1);gl.vertexAttrib3f(1,0,1,0);}if(pr.attributes.TEXCOORD_0!==undefined)bufferAttribute(accessorData(gltf,pr.attributes.TEXCOORD_0,true),2,2);else{gl.disableVertexAttribArray(2);gl.vertexAttrib2f(2,0,0);}let hasSkin=false;if(pr.attributes.JOINTS_0!==undefined&&pr.attributes.WEIGHTS_0!==undefined){const ja=json.accessors[pr.attributes.JOINTS_0],j=accessorData(gltf,pr.attributes.JOINTS_0,false),w=accessorData(gltf,pr.attributes.WEIGHTS_0,true);bufferAttribute(j,3,4,true,false,compInfo[ja.componentType][2]);bufferAttribute(w,4,4);hasSkin=true;}else{gl.disableVertexAttribArray(3);gl.vertexAttribI4ui(3,0,0,0,0);gl.disableVertexAttribArray(4);gl.vertexAttrib4f(4,1,0,0,0);}let index=null,indexType=0,count=posA.count;if(pr.indices!==undefined){const ia=json.accessors[pr.indices],id=accessorData(gltf,pr.indices,false),b=gl.createBuffer();gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER,b);gl.bufferData(gl.ELEMENT_ARRAY_BUFFER,id,gl.STATIC_DRAW);index=b;indexType=compInfo[ia.componentType][2];count=ia.count;}gl.bindVertexArray(null);primitives.push({meshIndex,vao,index,indexType,count,mode:pr.mode===5?gl.TRIANGLE_STRIP:pr.mode===6?gl.TRIANGLE_FAN:gl.TRIANGLES,material:(pr.material!==undefined?materials[pr.material]:null)||{color:new Float32Array([1,1,1,1]),texture:null,unlit:false,doubleSided:false},hasSkin,bounds:{min:posA.min||[-1,-1,-1],max:posA.max||[1,1,1]}});}));
  const nodes=(json.nodes||[]).map(n=>({mesh:n.mesh,skin:n.skin,children:n.children||[],baseT:(n.translation||[0,0,0]).slice(),baseR:(n.rotation||[0,0,0,1]).slice(),baseS:(n.scale||[1,1,1]).slice(),t:(n.translation||[0,0,0]).slice(),r:(n.rotation||[0,0,0,1]).slice(),s:(n.scale||[1,1,1]).slice(),matrix:n.matrix?new Float32Array(n.matrix):null,local:mat4Identity(),world:mat4Identity()}));
  const parents=new Int32Array(nodes.length);parents.fill(-1);nodes.forEach((n,i)=>n.children.forEach(c=>parents[c]=i));
  const roots=((json.scenes||[])[json.scene||0]?.nodes)||nodes.map((_,i)=>i).filter(i=>parents[i]===-1);
  const skins=(json.skins||[]).map(sk=>({joints:sk.joints,ibm:sk.inverseBindMatrices!==undefined?accessorData(gltf,sk.inverseBindMatrices,true):null}));
  const animations=(json.animations||[]).map((a,ai)=>{const samplers=a.samplers.map(s=>({input:accessorData(gltf,s.input,true),output:accessorData(gltf,s.output,true),interpolation:s.interpolation||'LINEAR',outputType:json.accessors[s.output].type}));let duration=0;samplers.forEach(s=>{if(s.input.length)duration=Math.max(duration,s.input[s.input.length-1]);});return {name:a.name||`Animasyon ${ai+1}`,duration,channels:a.channels.map(c=>({sampler:c.sampler,node:c.target.node,path:c.target.path})),samplers};});
  const sc={gltf,primitives,nodes,parents,roots,skins,animations};updateWorld(sc);fitCamera(sc);return sc;
}

function updateWorld(sc){
  sc.nodes.forEach(n=>{n.local=n.matrix?new Float32Array(n.matrix):V.mat4FromTRS(n.t,n.r,n.s);});
  const visit=(i,parent)=>{const n=sc.nodes[i];n.world=parent?mat4Multiply(parent,n.local):new Float32Array(n.local);n.children.forEach(c=>visit(c,n.world));};
  sc.roots.forEach(r=>visit(r,null));
}

function fitCamera(sc){let min=[Infinity,Infinity,Infinity],max=[-Infinity,-Infinity,-Infinity];sc.nodes.forEach(n=>{if(n.mesh===undefined)return;sc.primitives.filter(p=>p.meshIndex===n.mesh).forEach(p=>{const a=p.bounds.min,b=p.bounds.max;for(let x of [a[0],b[0]])for(let y of [a[1],b[1]])for(let z of [a[2],b[2]]){const q=transformPoint(n.world,[x,y,z]);for(let k=0;k<3;k++){min[k]=Math.min(min[k],q[k]);max[k]=Math.max(max[k],q[k]);}}});});if(!isFinite(min[0])){min=[-1,-1,-1];max=[1,1,1];}V.camera.target=[(min[0]+max[0])/2,(min[1]+max[1])/2,(min[2]+max[2])/2];V.camera.radius=Math.max(0.1,Math.hypot(max[0]-min[0],max[1]-min[1],max[2]-min[2])/2);V.camera.distance=V.camera.radius*2.6;}

function resetPose(sc){sc.nodes.forEach(n=>{n.t=n.baseT.slice();n.r=n.baseR.slice();n.s=n.baseS.slice();});updateWorld(sc);}

function animate(sc,time){resetPose(sc);if(V.activeAnimation<0||!V.playing)return;const a=sc.animations[V.activeAnimation];if(!a||a.duration<=0)return;const t=(time-V.animationStarted)%a.duration;for(const ch of a.channels){const s=a.samplers[ch.sampler],input=s.input,count=input.length;if(!count)continue;let i=0;while(i<count-2&&input[i+1]<=t)i++;const t0=input[i],t1=input[Math.min(i+1,count-1)],mix=s.interpolation==='STEP'||t1<=t0?0:(t-t0)/(t1-t0),n=sc.nodes[ch.node],components=ch.path==='rotation'?4:3,o0=i*components,o1=Math.min(i+1,count-1)*components,a0=Array.from(s.output.slice(o0,o0+components)),a1=Array.from(s.output.slice(o1,o1+components));if(ch.path==='rotation')n.r=quatSlerp(a0,a1,mix);else{const v=a0.map((v,k)=>v+(a1[k]-v)*mix);if(ch.path==='translation')n.t=v;else n.s=v;}}updateWorld(sc);}

function jointMatrices(sc,node){const skin=sc.skins[node.skin];if(!skin)return null;const invMesh=mat4Invert(node.world),out=new Float32Array(MAX_JOINTS*16);for(let i=0;i<MAX_JOINTS;i++)out.set(mat4Identity(),i*16);skin.joints.forEach((j,i)=>{if(i>=MAX_JOINTS)return;let ibm=mat4Identity();if(skin.ibm)ibm=new Float32Array(skin.ibm.slice(i*16,i*16+16));out.set(mat4Multiply(mat4Multiply(invMesh,sc.nodes[j].world),ibm),i*16);});return out;}
Object.assign(V,{parseGlb,accessorData,buildScene,updateWorld,fitCamera,resetPose,animate,jointMatrices});
})();
