// helper: file to base64
function fileToBase64(file){
  return new Promise((res, rej) => {
    const fr = new FileReader();
    fr.onload = () => res(fr.result);
    fr.onerror = rej;
    fr.readAsDataURL(file);
  });
}

// render preview snippet on right panel
async function renderReportPreview(){
  const dist = computeDistance(points);

  // thumbs das fotos (URLs temporárias)
  const thumbs = photoFiles.map(f => {
    const url = URL.createObjectURL(f);
    return `<img src="${url}" style="width:70px;height:56px;object-fit:cover;border-radius:6px;margin-right:6px;border:1px solid #eee">`;
  }).join('');

  const sampleCoords = points.slice(0,6).map((p,i) => `${i+1}) ${p.lat.toFixed(6)}, ${p.lng.toFixed(6)}`).join('<br>') || '—';

  const html = `
    <div style="display:flex;gap:12px;align-items:center">
      <div style="flex:1">
        <div style="font-weight:700">Resumo</div>
        <div class="small">Distância: <strong>${formatDistance(dist)}</strong></div>
        <div class="small">Pontos: <strong>${points.length}</strong></div>
        <div class="small" style="margin-top:8px">Avaliação: <strong>${rating}/5</strong></div>
        <div style="margin-top:8px;color:var(--muted)">${(commentEl.value||'—')}</div>
      </div>
      <div>${thumbs || '<div class="small">Sem fotos</div>'}</div>
    </div>
    <hr style="margin:10px 0">
    <div style="font-weight:700;margin-bottom:6px">Coordenadas (amostra)</div>
    <div class="small">${sampleCoords}</div>
  `;

  reportPreview.innerHTML = html;

  // revoga URLs de objeto para liberar memória após curto atraso (se desejar)
  setTimeout(() => {
    photoFiles.forEach(f => {
      try { URL.revokeObjectURL(f && f.preview); } catch(e){}
    });
  }, 5000);
}

// initial
updateMap();
updateStars();
renderReportPreview();
