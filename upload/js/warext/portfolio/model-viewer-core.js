(() => {
'use strict';

const root = document.querySelector('[data-wrxt-model-viewer]');
if (!root) return;
const canvas = root.querySelector('canvas');
const statusEl = root.querySelector('[data-status]');
const errorEl = root.querySelector('[data-error]');
const animationSelect = root.querySelector('[data-animation]');
const resetButton = root.querySelector('[data-action="reset"]');
const playButton = root.querySelector('[data-action="play"]');
const fullscreenButton = root.querySelector('[data-action="fullscreen"]');
const modelUrl = root.dataset.modelUrl || '';
const gl = canvas.getContext('webgl2', { alpha: false, antialias: true, depth: true, powerPreference: 'high-performance' });
if (!gl) return showError('WebGL2 bu tarayıcıda kullanılamıyor.');

const MAX_JOINTS = 48;
function showError(message) {
  if (statusEl) statusEl.textContent = 'Hata';
  errorEl.textContent = message;
  errorEl.classList.add('is-active');
}

function setStatus(message) {
  if (statusEl) statusEl.textContent = message;
}

function mat4Identity() {
  return new Float32Array([1,0,0,0,0,1,0,0,0,0,1,0,0,0,0,1]);
}

function mat4Multiply(a, b) {
  const o = new Float32Array(16);
  for (let c = 0; c < 4; c++) {
    for (let r = 0; r < 4; r++) {
      o[c*4+r] = a[0*4+r]*b[c*4+0] + a[1*4+r]*b[c*4+1] + a[2*4+r]*b[c*4+2] + a[3*4+r]*b[c*4+3];
    }
  }
  return o;
}

function mat4Perspective(fovy, aspect, near, far) {
  const f = 1 / Math.tan(fovy / 2);
  const nf = 1 / (near - far);
  return new Float32Array([f/aspect,0,0,0,0,f,0,0,0,0,(far+near)*nf,-1,0,0,2*far*near*nf,0]);
}

function vec3Normalize(v) {
  const l = Math.hypot(v[0], v[1], v[2]) || 1;
  return [v[0]/l, v[1]/l, v[2]/l];
}

function vec3Cross(a,b) {
  return [a[1]*b[2]-a[2]*b[1], a[2]*b[0]-a[0]*b[2], a[0]*b[1]-a[1]*b[0]];
}

function mat4LookAt(eye, center, up) {
  const z = vec3Normalize([eye[0]-center[0], eye[1]-center[1], eye[2]-center[2]]);
  const x = vec3Normalize(vec3Cross(up, z));
  const y = vec3Cross(z, x);
  return new Float32Array([
    x[0],y[0],z[0],0,
    x[1],y[1],z[1],0,
    x[2],y[2],z[2],0,
    -(x[0]*eye[0]+x[1]*eye[1]+x[2]*eye[2]),
    -(y[0]*eye[0]+y[1]*eye[1]+y[2]*eye[2]),
    -(z[0]*eye[0]+z[1]*eye[1]+z[2]*eye[2]),1
  ]);
}

function mat4FromTRS(t, q, s) {
  const x=q[0],y=q[1],z=q[2],w=q[3];
  const x2=x+x,y2=y+y,z2=z+z;
  const xx=x*x2,xy=x*y2,xz=x*z2,yy=y*y2,yz=y*z2,zz=z*z2,wx=w*x2,wy=w*y2,wz=w*z2;
  return new Float32Array([
    (1-(yy+zz))*s[0],(xy+wz)*s[0],(xz-wy)*s[0],0,
    (xy-wz)*s[1],(1-(xx+zz))*s[1],(yz+wx)*s[1],0,
    (xz+wy)*s[2],(yz-wx)*s[2],(1-(xx+yy))*s[2],0,
    t[0],t[1],t[2],1
  ]);
}

function mat4Invert(a) {
  const o = new Float32Array(16);
  const a00=a[0],a01=a[1],a02=a[2],a03=a[3],a10=a[4],a11=a[5],a12=a[6],a13=a[7],a20=a[8],a21=a[9],a22=a[10],a23=a[11],a30=a[12],a31=a[13],a32=a[14],a33=a[15];
  const b00=a00*a11-a01*a10,b01=a00*a12-a02*a10,b02=a00*a13-a03*a10,b03=a01*a12-a02*a11,b04=a01*a13-a03*a11,b05=a02*a13-a03*a12,b06=a20*a31-a21*a30,b07=a20*a32-a22*a30,b08=a20*a33-a23*a30,b09=a21*a32-a22*a31,b10=a21*a33-a23*a31,b11=a22*a33-a23*a32;
  let det=b00*b11-b01*b10+b02*b09+b03*b08-b04*b07+b05*b06;
  if (!det) return mat4Identity();
  det=1/det;
  o[0]=(a11*b11-a12*b10+a13*b09)*det;o[1]=(a02*b10-a01*b11-a03*b09)*det;o[2]=(a31*b05-a32*b04+a33*b03)*det;o[3]=(a22*b04-a21*b05-a23*b03)*det;
  o[4]=(a12*b08-a10*b11-a13*b07)*det;o[5]=(a00*b11-a02*b08+a03*b07)*det;o[6]=(a32*b02-a30*b05-a33*b01)*det;o[7]=(a20*b05-a22*b02+a23*b01)*det;
  o[8]=(a10*b10-a11*b08+a13*b06)*det;o[9]=(a01*b08-a00*b10-a03*b06)*det;o[10]=(a30*b04-a31*b02+a33*b00)*det;o[11]=(a21*b02-a20*b04-a23*b00)*det;
  o[12]=(a11*b07-a10*b09-a12*b06)*det;o[13]=(a00*b09-a01*b07+a02*b06)*det;o[14]=(a31*b01-a30*b03-a32*b00)*det;o[15]=(a20*b03-a21*b01+a22*b00)*det;
  return o;
}

function transformPoint(m, p) {
  return [m[0]*p[0]+m[4]*p[1]+m[8]*p[2]+m[12],m[1]*p[0]+m[5]*p[1]+m[9]*p[2]+m[13],m[2]*p[0]+m[6]*p[1]+m[10]*p[2]+m[14]];
}

function quatNormalize(q) {
  const l = Math.hypot(q[0],q[1],q[2],q[3]) || 1;
  return [q[0]/l,q[1]/l,q[2]/l,q[3]/l];
}

function quatSlerp(a,b,t) {
  let cos=a[0]*b[0]+a[1]*b[1]+a[2]*b[2]+a[3]*b[3];
  let bb=b;
  if (cos<0) { cos=-cos; bb=[-b[0],-b[1],-b[2],-b[3]]; }
  if (cos>0.9995) return quatNormalize([a[0]+t*(bb[0]-a[0]),a[1]+t*(bb[1]-a[1]),a[2]+t*(bb[2]-a[2]),a[3]+t*(bb[3]-a[3])]);
  const theta=Math.acos(Math.max(-1,Math.min(1,cos))), sin=Math.sin(theta);
  const s0=Math.sin((1-t)*theta)/sin,s1=Math.sin(t*theta)/sin;
  return [a[0]*s0+bb[0]*s1,a[1]*s0+bb[1]*s1,a[2]*s0+bb[2]*s1,a[3]*s0+bb[3]*s1];
}

function compileShader(type, source) {
  const sh=gl.createShader(type); gl.shaderSource(sh,source); gl.compileShader(sh);
  if (!gl.getShaderParameter(sh,gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(sh)||'Shader hatası');
  return sh;
}

function createProgram() {
  const vs=`#version 300 es
precision highp float; precision highp int;
layout(location=0) in vec3 aPosition; layout(location=1) in vec3 aNormal; layout(location=2) in vec2 aUv; layout(location=3) in uvec4 aJoints; layout(location=4) in vec4 aWeights;
uniform mat4 uProjection; uniform mat4 uView; uniform mat4 uModel; uniform bool uHasSkin; uniform mat4 uJoints[${MAX_JOINTS}];
out vec3 vNormal; out vec2 vUv;
void main(){mat4 skin=mat4(1.0);if(uHasSkin){skin=aWeights.x*uJoints[int(aJoints.x)]+aWeights.y*uJoints[int(aJoints.y)]+aWeights.z*uJoints[int(aJoints.z)]+aWeights.w*uJoints[int(aJoints.w)];}mat4 world=uModel*skin;vec4 wp=world*vec4(aPosition,1.0);gl_Position=uProjection*uView*wp;vNormal=mat3(world)*aNormal;vUv=aUv;}`;
  const fs=`#version 300 es
precision highp float; in vec3 vNormal; in vec2 vUv; uniform vec4 uBaseColor; uniform sampler2D uTexture; uniform bool uHasTexture; uniform bool uUnlit; out vec4 outColor;
void main(){vec4 c=uBaseColor;if(uHasTexture)c*=texture(uTexture,vUv);if(c.a<0.02)discard;float light=1.0;if(!uUnlit){vec3 n=normalize(vNormal);light=0.28+0.72*max(dot(n,normalize(vec3(0.45,0.8,0.35))),0.0);}outColor=vec4(c.rgb*light,c.a);}`;
  const p=gl.createProgram(); gl.attachShader(p,compileShader(gl.VERTEX_SHADER,vs)); gl.attachShader(p,compileShader(gl.FRAGMENT_SHADER,fs)); gl.linkProgram(p);
  if (!gl.getProgramParameter(p,gl.LINK_STATUS)) throw new Error(gl.getProgramInfoLog(p)||'Program hatası');
  return p;
}

const program=createProgram();
const uniforms={projection:gl.getUniformLocation(program,'uProjection'),view:gl.getUniformLocation(program,'uView'),model:gl.getUniformLocation(program,'uModel'),hasSkin:gl.getUniformLocation(program,'uHasSkin'),joints:gl.getUniformLocation(program,'uJoints[0]'),baseColor:gl.getUniformLocation(program,'uBaseColor'),texture:gl.getUniformLocation(program,'uTexture'),hasTexture:gl.getUniformLocation(program,'uHasTexture'),unlit:gl.getUniformLocation(program,'uUnlit')};
window.WrxtPortfolioViewer={root,canvas,statusEl,errorEl,animationSelect,resetButton,playButton,fullscreenButton,modelUrl,gl,MAX_JOINTS,scene:null,camera:{yaw:.65,pitch:.35,distance:4,target:[0,0,0],radius:1},drag:null,playing:true,activeAnimation:-1,animationStarted:performance.now()/1000,lastFrame:0,showError,setStatus,mat4Identity,mat4Multiply,mat4Perspective,vec3Normalize,vec3Cross,mat4LookAt,mat4FromTRS,mat4Invert,transformPoint,quatNormalize,quatSlerp,compileShader,createProgram,program,uniforms};
})();
