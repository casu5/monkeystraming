async function cargarCatalogo(){
    
const res = await fetch('api/contenidos.php');
const items = await res.json();
const grid = document.getElementById('catalogo');
if(!grid) return;
grid.innerHTML = '';
items.forEach(it => {
const el = document.createElement('article');
el.className = 'card';
el.innerHTML = `
<img src="${it.thumbnail || 'https://via.placeholder.com/400x240?text=Thumb'}" alt="">
<h3>${it.titulo}</h3>
<a href="player.php?id=${it.id}">Ver</a>
`;
grid.appendChild(el);
});
}


async function obtenerSaldo(){
try{
const res = await fetch('api/saldo.php');
const data = await res.json();
if(data.ok){
const el = document.getElementById('saldo');
if(el) el.textContent = 'Saldo: ' + data.saldo.toFixed(2);
}
}catch(e){ /* no hacer ruido */ }
}



// Inicializar
document.addEventListener('DOMContentLoaded', ()=>{
cargarCatalogo();
obtenerSaldo();
setInterval(obtenerSaldo, 3000); // polling cada 3s
});
