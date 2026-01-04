import { open, confirm } from "@tauri-apps/plugin-dialog";
import { ENGINE_BASE_URL, ENGINE_API_PREFIX } from "./config";

const chooseBtn = document.getElementById("choose-folder");
const startScanBtn = document.getElementById("start-scan");
const buildPlanBtn = document.getElementById("build-plan");
const applyPlanBtn = document.getElementById("apply-plan");
const undoApplyBtn = document.getElementById("undo-apply");

const pathEl = document.getElementById("folder-path");

const scanStatusEl = document.getElementById("scan-status");
const scanProgressBar = document.getElementById("scan-progress-bar");

const planStatusEl = document.getElementById("plan-status");
const planPreviewEl = document.getElementById("plan-preview");

const applyStatusEl = document.getElementById("apply-status");
const applyProgressBar = document.getElementById("apply-progress-bar");
const applySummaryEl = document.getElementById("apply-summary");

const applyWarningEl = document.getElementById("apply-warning");

let selectedPath = null;
let scanId = null;
let planId = null;
let applyId = null;

let scanPollTimer = null;
let applyPollTimer = null;

function apiUrl(path) {
  return `${ENGINE_BASE_URL}${ENGINE_API_PREFIX}${path}`;
}

function resetAfterNewFolder() {
  scanId = null;
  planId = null;
  applyId = null;

  if (scanPollTimer) clearInterval(scanPollTimer);
  if (applyPollTimer) clearInterval(applyPollTimer);

  scanStatusEl.textContent = "Scan : —";
  scanProgressBar.style.width = "0%";

  planStatusEl.textContent = "Plan : —";
  planPreviewEl.textContent = "Aucune action";

  applyStatusEl.textContent = "Apply : —";
  applyProgressBar.style.width = "0%";
  applySummaryEl.textContent = "Résumé : —";

  startScanBtn.disabled = !selectedPath;
  buildPlanBtn.disabled = true;

  while (!applyWarningEl.hidden) {
    applyPlanBtn.disabled = true;
    undoApplyBtn.disabled = true;
  }
  
}

async function postJson(url, payload) {
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const json = await res.json();
  return { res, json };
}

async function getJson(url) {
  const res = await fetch(url);
  const json = await res.json();
  return { res, json };
}

// 1) Choisir un dossier
chooseBtn.addEventListener("click", async () => {
  const result = await open({ directory: true, multiple: false });
  if (typeof result === "string") {
    selectedPath = result;
    pathEl.textContent = result;
    resetAfterNewFolder();
  }
});

// 2) Lancer Scan
startScanBtn.addEventListener("click", async () => {
  if (!selectedPath) return;

  startScanBtn.disabled = true;
  buildPlanBtn.disabled = true;
  applyPlanBtn.disabled = true;
  undoApplyBtn.disabled = true;

  scanStatusEl.textContent = "Scan : lancement...";
  scanProgressBar.style.width = "0%";

  const { json } = await postJson(apiUrl("/scans"), { path: selectedPath });

  if (!json.ok) {
    scanStatusEl.textContent = `Scan : erreur (${json.error.code}) ${json.error.message}`;
    startScanBtn.disabled = false;
    return;
  }

  scanId = json.data.scanId;
  scanStatusEl.textContent = `Scan : ${json.data.status} (${scanId})`;

  scanPollTimer = setInterval(pollScan, 900);
});

async function pollScan() {
  if (!scanId) return;

  const { json } = await getJson(apiUrl(`/scans/${scanId}`));

  if (!json.ok) {
    scanStatusEl.textContent = `Scan : erreur (${json.error.code}) ${json.error.message}`;
    clearInterval(scanPollTimer);
    startScanBtn.disabled = false;
    return;
  }

  const d = json.data;
  scanStatusEl.textContent = `Scan : ${d.status} (${d.progress.percent}%)`;
  scanProgressBar.style.width = `${d.progress.percent}%`;

  if (d.status === "done") {
    clearInterval(scanPollTimer);
    scanStatusEl.textContent = `Scan : done (${scanId})`;
    buildPlanBtn.disabled = false;
    startScanBtn.disabled = false;
  }
}

// 3) Construire Plan
buildPlanBtn.addEventListener("click", async () => {
  if (!scanId) return;

  buildPlanBtn.disabled = true;
  applyPlanBtn.disabled = true;
  undoApplyBtn.disabled = true;

  planStatusEl.textContent = "Plan : création...";
  planPreviewEl.textContent = "Chargement...";

  const { json } = await postJson(apiUrl("/plans"), {
    scanId,
    template: "downloads_basic",
  });

  if (!json.ok) {
    planStatusEl.textContent = `Plan : erreur (${json.error.code}) ${json.error.message}`;
    buildPlanBtn.disabled = false;
    return;
  }

  planId = json.data.planId;
  planStatusEl.textContent = `Plan : ${json.data.status} (${planId})`;

  const preview = await getJson(apiUrl(`/plans/${planId}`));
  if (!preview.json.ok) {
    planStatusEl.textContent = `Plan : erreur preview (${preview.json.error.code}) ${preview.json.error.message}`;
    buildPlanBtn.disabled = false;
    return;
  }

  planPreviewEl.textContent = JSON.stringify(preview.json.data.actions, null, 2);
  const actions = preview.json.data.actions || [];
  const counts = actions.reduce(
    (acc, a) => {
      acc[a.type] = (acc[a.type] || 0) + 1;
      return acc;
    },
    {}
  );

  planStatusEl.textContent +=
    ` — ${counts.move || 0} fichiers, ${counts.mkdir || 0} dossiers`;

  applyPlanBtn.disabled = false;
  applyWarningEl.hidden = false;//L’utilisateur voit maintenant l’avertissement AVANT Apply.

});

// 4) Apply
applyPlanBtn.addEventListener("click", async (e) => {
  e.preventDefault();
  e.stopPropagation();

  if (!planId) return;

  const ok = await confirm(
    "Cette action va modifier les fichiers sur votre disque.\n\nSouhaitez-vous vraiment appliquer ce plan ?",
    { title: "HESTIA", kind: "warning" }
  );

  if (!ok) {
    console.log("Apply cancelled by user");
    return;
  }

  console.log("Apply confirmed by user");

  applyPlanBtn.disabled = true;
  undoApplyBtn.disabled = true;

  applyStatusEl.textContent = "Apply : lancement...";
  applyProgressBar.style.width = "0%";
  applySummaryEl.textContent = "Résumé : —";

  const { json } = await postJson(apiUrl("/applies"), { planId });

  if (!json.ok) {
    applyStatusEl.textContent = `Apply : erreur (${json.error.code}) ${json.error.message}`;
    applyPlanBtn.disabled = false;
    return;
  }

  applyId = json.data.applyId;
  applyStatusEl.textContent = `Apply : ${json.data.status} (${applyId})`;

  undoApplyBtn.disabled = false;
  applyPollTimer = setInterval(pollApply, 900);
});


async function pollApply() {
  if (!applyId) return;

  const { json } = await getJson(apiUrl(`/applies/${applyId}`));

  if (!json.ok) {
    applyStatusEl.textContent = `Apply : erreur (${json.error.code}) ${json.error.message}`;
    clearInterval(applyPollTimer);
    applyPlanBtn.disabled = false;
    return;
  }

  const d = json.data;
  applyStatusEl.textContent = `Apply : ${d.status} (${d.progress.percent}%)`;
  applyProgressBar.style.width = `${d.progress.percent}%`;
  applySummaryEl.textContent = JSON.stringify(d.summary, null, 2);

  if (d.status === "done") {
    clearInterval(applyPollTimer);
    applyStatusEl.textContent = `Apply : done (${applyId})`;
    applyPlanBtn.disabled = false;
  }
}

// 5) Undo
undoApplyBtn.addEventListener("click", async () => {
  if (!applyId) return;

  undoApplyBtn.disabled = true;
  applyStatusEl.textContent = "Undo : lancement...";

  const { json } = await postJson(apiUrl("/undo"), { applyId });

  if (!json.ok) {
    applyStatusEl.textContent = `Undo : erreur (${json.error.code}) ${json.error.message}`;
    undoApplyBtn.disabled = false;
    return;
  }

  applyStatusEl.textContent =
  "Undo : terminé. Les fichiers ont été restaurés (dans la mesure du possible).";
  //applyStatusEl.textContent = `Undo : ${json.data.status} (applyId=${json.data.applyId})`;
  // Stub: on n'a pas encore endpoint GET /undo, donc on reste simple
  setTimeout(() => {
    applyStatusEl.textContent = `Undo : done (stub)`;
    undoApplyBtn.disabled = false;
  }, 900);
});
