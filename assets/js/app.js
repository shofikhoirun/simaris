// ===== SIMARIS - Main JS =====

// Toggle sidebar (mobile)
function toggleSidebar(){
    document.getElementById('sidebar')?.classList.toggle('open');
}

// Modal control
function openModal(id){
    const m = document.getElementById(id);
    if(m) m.classList.add('show');
}
function closeModal(id){
    const m = document.getElementById(id);
    if(m) m.classList.remove('show');
}

// Confirm delete
function confirmDelete(url, msg){
    if(confirm(msg || 'Yakin ingin menghapus data ini?')){
        window.location.href = url;
    }
}

// Auto-calculate skor risiko on form
function calcSkor(prefix=''){
    const lh = parseInt(document.getElementById(prefix+'likelihood')?.value || 0);
    const im = parseInt(document.getElementById(prefix+'impact')?.value || 0);
    if(lh && im){
        // Bobot dari matriks 5x5
        const matriks = {
            '1-1':1,'1-2':2,'1-3':3,'1-4':4,'1-5':5,
            '2-1':2,'2-2':3,'2-3':4,'2-4':5,'2-5':6,
            '3-1':3,'3-2':4,'3-3':5,'3-4':6,'3-5':7,
            '4-1':4,'4-2':5,'4-3':6,'4-4':7,'4-5':8,
            '5-1':5,'5-2':6,'5-3':7,'5-4':8,'5-5':9
        };
        const bobot = matriks[lh+'-'+im] || 1;
        const nilai = lh * im * bobot;
        let tingkat = 'sangat_rendah', label = 'Sangat Rendah';
        if(nilai>140){ tingkat='sangat_tinggi'; label='Sangat Tinggi'; }
        else if(nilai>80){ tingkat='tinggi'; label='Tinggi'; }
        else if(nilai>40){ tingkat='sedang'; label='Sedang'; }
        else if(nilai>15){ tingkat='rendah'; label='Rendah'; }

        const bb = document.getElementById(prefix+'bobot');
        const nn = document.getElementById(prefix+'nilai');
        const tt = document.getElementById(prefix+'tingkat');
        const tl = document.getElementById(prefix+'tingkat_label');
        if(bb) bb.value = bobot;
        if(nn) nn.value = nilai;
        if(tt) tt.value = tingkat;
        if(tl){
            tl.textContent = label;
            tl.className = 'badge badge-' +
                (tingkat==='sangat_tinggi'?'danger':
                 tingkat==='tinggi'?'orange':
                 tingkat==='sedang'?'warning':'success');
        }
    }
}

// Print only printable area
function printPage(){ window.print(); }

// Auto close alerts after 5s
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(a => {
            a.style.transition = 'opacity .5s';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 500);
        });
    }, 5000);
});
