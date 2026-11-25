import * as THREE from 'three';

// An original, stylized mesh portrait based on Sheen's supplied reference:
// parted black hair, thin glasses, charcoal blazer, white shirt, light trousers.
export function createAvatar(initialReducedMotion) {
  const container = document.querySelector('#avatar-scene');
  const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true, powerPreference: 'low-power' });
  renderer.setPixelRatio(Math.min(devicePixelRatio, 1.7));
  renderer.setClearColor(0, 0);
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.2;
  container.append(renderer.domElement);
  container.querySelector('.avatar-fallback').remove();
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(30, 1, .1, 100);
  camera.position.set(0, 1.85, 10.5);
  camera.lookAt(0, .55, 0);
  scene.add(new THREE.HemisphereLight(0xdceaff, 0x71665e, 2.5));
  const key = new THREE.DirectionalLight(0xffeee0, 4.5); key.position.set(-3, 6, 6); scene.add(key);
  const rim = new THREE.DirectionalLight(0x7caeff, 5); rim.position.set(4, 2, -3); scene.add(rim);
  const fill = new THREE.DirectionalLight(0xffffff, 1.2); fill.position.set(3, 2, 4); scene.add(fill);
  const material = (color, roughness = .65, metalness = 0) => new THREE.MeshStandardMaterial({ color, roughness, metalness });
  const skin = material(0xc28c70, .72), skinShadow = material(0xb47c64), hair = material(0x111419, .46);
  const jacket = material(0x384454, .85), lapel = material(0x49596c, .7), shirt = material(0xe9edf4);
  const trousers = material(0xaab5c3), shoes = material(0x242a32, .4), frames = material(0xb7bec7, .27, .75);
  const eyeWhite = material(0xe4dbd4), iris = material(0x352c28), black = material(0x141719);
  const character = new THREE.Group(); scene.add(character); character.position.set(-.1, .15, 0);
  function ellipsoid(parent, mat, pos, scale) {
    const mesh = new THREE.Mesh(new THREE.SphereGeometry(1, 32, 24), mat);
    mesh.position.set(...pos); mesh.scale.set(...scale); parent.add(mesh); return mesh;
  }
  function capsule(parent, mat, pos, radius, length, rotation = 0) {
    const mesh = new THREE.Mesh(new THREE.CapsuleGeometry(radius, length, 8, 16), mat);
    mesh.position.set(...pos); mesh.rotation.z = rotation; parent.add(mesh); return mesh;
  }
  function line(parent, points, mat, radius = .012) {
    const curve = new THREE.CatmullRomCurve3(points.map(p => new THREE.Vector3(...p)));
    const mesh = new THREE.Mesh(new THREE.TubeGeometry(curve, 32, radius, 8, false), mat); parent.add(mesh); return mesh;
  }
  // Shoes, tailored trousers, and blazer body.
  for (const x of [-.26,.26]) {
    capsule(character, trousers, [x,-1.18,0], .21, .9, x * -.1);
    ellipsoid(character, shoes, [x,-1.85,.15], [.24,.14,.39]);
  }
  ellipsoid(character, jacket, [0,-.02,0], [.63,.83,.34]);
  ellipsoid(character, shirt, [0,.19,.272], [.31,.55,.092]);
  capsule(character, skin, [0,.81,0], .19, .21);
  // Lapels are custom triangles with a softly rounded silhouette.
  function lapelShape(sign) {
    const shape = new THREE.Shape();
    shape.moveTo(sign*.22,.72); shape.lineTo(sign*.48,.51); shape.lineTo(sign*.34,.25); shape.lineTo(sign*.4,.16); shape.lineTo(sign*.13,-.36); shape.closePath();
    const geometry = new THREE.ExtrudeGeometry(shape,{depth:.035,bevelEnabled:true,bevelSegments:2,steps:1,bevelSize:.015,bevelThickness:.015});
    const mesh = new THREE.Mesh(geometry,lapel); mesh.position.z=.30; character.add(mesh);
  }
  lapelShape(-1);lapelShape(1);
  ellipsoid(character, black, [.12,-.38,.343],[.035,.035,.02]);
  line(character,[[.36,-.26,.32],[.54,-.23,.25]],lapel,.018);
  // Relaxed left arm.
  capsule(character,jacket,[-.64,.05,0],.19,.67,-.13);
  capsule(character,jacket,[-.72,-.53,.04],.16,.35,-.08);
  ellipsoid(character,skin,[-.74,-.88,.07],[.15,.22,.13]);
  // Wave shoulder rotates as a real articulated group; fingers share the wrist.
  const waveArm = new THREE.Group(); waveArm.position.set(.53,.44,0); waveArm.rotation.z=1.3; character.add(waveArm);
  capsule(waveArm,jacket,[0,-.32,0],.19,.43);
  const forearm = new THREE.Group(); forearm.position.set(0,-.61,0); forearm.rotation.z=1.6; waveArm.add(forearm);
  capsule(forearm,jacket,[0,-.26,0],.16,.35);
  capsule(forearm,shirt,[0,-.48,0],.137,.035);
  const hand = new THREE.Group(); hand.position.set(0,-.64,0); forearm.add(hand);
  ellipsoid(hand,skin,[0,0,0],[.17,.20,.09]);
  const fingers=[[-.13,-.22,.145,-.16],[-.045,-.27,.20,-.04],[.045,-.26,.19,.07],[.125,-.22,.14,.22]];
  for(const [x,y,len,angle] of fingers) capsule(hand,skin,[x,y,0],.044,len,angle);
  capsule(hand,skin,[-.20,-.035,.005],.06,.16,-.7);
  // Head and facial features, with a softly squared face and ears.
  const head = new THREE.Group(); head.position.set(0,1.35,0); character.add(head);
  ellipsoid(head,skin,[0,0,0],[.52,.65,.44]);
  // A single continuous face surface avoids intersecting cheek geometry.
  for(const sign of [-1,1]) {
    ellipsoid(head,skin,[sign*.51,-.02,0],[.105,.18,.095]);
    ellipsoid(head,skinShadow,[sign*.54,-.025,.065],[.035,.09,.025]);
    ellipsoid(head,eyeWhite,[sign*.205,.075,.407],[.115,.060,.035]);
    ellipsoid(head,iris,[sign*.205,.075,.438],[.042,.048,.017]);
    ellipsoid(head,black,[sign*.205,.075,.451],[.022,.031,.009]);
    ellipsoid(head,shirt,[sign*.205-.012,.091,.46],[.010,.013,.006]);
    line(head,[[sign*.09,.205,.42],[sign*.19,.23,.43],[sign*.31,.205,.39]],hair,.022);
  }
  ellipsoid(head,skin,[0,-.04,.442],[.075,.13,.1]);
  ellipsoid(head,skinShadow,[0,-.105,.49],[.071,.031,.025]);
  line(head,[[-.135,-.265,.385],[0,-.285,.413],[.135,-.26,.385]],material(0x79534b),.014);
  line(head,[[-.08,-.306,.385],[0,-.318,.393],[.08,-.305,.385]],material(0xd6a38c),.012);
  // Thin rectangular glasses; no opaque lens to hide the eyes.
  for(const sign of [-1,1]) {
    const x=sign*.215;
    line(head,[[x-.16,.155,.462],[x-.17,.01,.462],[x-.12,-.055,.465],[x+.12,-.055,.465],[x+.17,.01,.462],[x+.16,.155,.462],[x-.16,.155,.462]],frames,.012);
    line(head,[[sign*.38,.10,.45],[sign*.52,.11,.04]],frames,.011);
  }
  line(head,[[-.05,.07,.472],[0,.10,.48],[.05,.07,.472]],frames,.012);
  // Sculpted, side-parted hair using overlapping swept locks.
  ellipsoid(head,hair,[0,.37,-.06],[.55,.38,.44]);
  ellipsoid(head,hair,[0,.11,-.29],[.49,.46,.20]);
  for(let i=0;i<7;i++) {
    const lock=ellipsoid(head,hair,[-.28+i*.071,.44-i*.017,.25],[.15,.32,.15]);
    lock.rotation.z=-.6+i*.04; lock.rotation.x=-.25;
  }
  for(let i=0;i<4;i++) {
    const lock=ellipsoid(head,hair,[.22+i*.062,.36-i*.07,.25],[.12,.27,.13]);
    lock.rotation.z=.65; lock.rotation.x=-.15;
  }
  ellipsoid(head,hair,[-.465,.15,.03],[.07,.24,.18]);
  ellipsoid(head,hair,[.465,.15,.03],[.07,.22,.18]);
  // Floating base and fine orbit ring anchor the model in the scene.
  const platform=new THREE.Mesh(new THREE.CylinderGeometry(.91,.94,.09,64),material(0x516782,.35,.6));
  platform.position.set(-.1,-1.91,0);scene.add(platform);
  const ring=new THREE.Mesh(new THREE.TorusGeometry(1.22,.009,8,100),material(0xa8caff,.3,.5));
  ring.rotation.x=Math.PI/2;ring.position.set(-.1,-1.94,0);scene.add(ring);
  let reduced=initialReducedMotion, visible=true, frame=0, waveUntil=performance.now()+4000;
  const pointer=new THREE.Vector2(); let targetRotation=0;
  container.addEventListener('pointermove',event=>{
    const r=container.getBoundingClientRect();pointer.set((event.clientX-r.left)/r.width-.5,(event.clientY-r.top)/r.height-.5);
  });
  container.addEventListener('pointerleave',()=>pointer.set(0,0));
  const waveButton=document.querySelector('#wave-button');
  waveButton.addEventListener('click',()=>{
    waveUntil=performance.now()+3000;
    if(reduced){waveButton.textContent='Hello, nice to meet you!';return;}
    start();
  });
  function render(now=0){
    frame=0;
    const t=now/1000;
    if(!reduced){
      targetRotation+=(pointer.x*.35-targetRotation)*.045;
      character.rotation.y=-.14+targetRotation;
      character.position.y=.15+Math.sin(t*1.2)*.035;
      head.rotation.z=Math.sin(t*.65)*.018;
      head.rotation.x=pointer.y*.07;
      const waving=now<waveUntil;
      waveArm.rotation.z=1.3+(waving?Math.sin(t*8)*.16:Math.sin(t*1.1)*.025);
      hand.rotation.z=waving?Math.sin(t*8)*.18:0;
    }
    renderer.render(scene,camera);
    if(!reduced&&visible&&!document.hidden)frame=requestAnimationFrame(render);
  }
  function start(){if(!frame&&visible&&!document.hidden)frame=requestAnimationFrame(render);}
  const resize=new ResizeObserver(()=>{
    const {width,height}=container.getBoundingClientRect();
    renderer.setSize(width,height);camera.aspect=width/height;camera.updateProjectionMatrix();start();
  });resize.observe(container);
  const visibility=new IntersectionObserver(([entry])=>{
    visible=entry.isIntersecting;
    if(visible)start();else if(frame){cancelAnimationFrame(frame);frame=0;}
  });visibility.observe(container);
  document.addEventListener('visibilitychange',()=>{
    if(document.hidden){cancelAnimationFrame(frame);frame=0;}else start();
  });
  window.addEventListener('portfolio:motion',event=>{
    reduced=event.detail;
    if(reduced){cancelAnimationFrame(frame);frame=0;character.rotation.y=-.14;waveArm.rotation.z=1.3;hand.rotation.z=0;}
    start();
  });
  renderer.domElement.addEventListener('webglcontextlost',event=>{event.preventDefault();cancelAnimationFrame(frame);frame=0;});
  renderer.domElement.addEventListener('webglcontextrestored',start);
  start();
}
