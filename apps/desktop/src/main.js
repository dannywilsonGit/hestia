import { open, confirm } from "@tauri-apps/plugin-dialog";
import { ENGINE_BASE_URL, ENGINE_API_PREFIX } from "./config";
import { openPath, openUrl } from '@tauri-apps/plugin-opener';

const chooseBtn = document.getElementById("choose-folder");
const startScanBtn = document.getElementById("start-scan");
const buildPlanBtn = document.getElementById("build-plan");
const applyPlanBtn = document.getElementById("apply-plan");
const undoApplyBtn = document.getElementById("undo-apply");

const pathEl = document.getElementById("folder-path");
const InexplorerBtns = document.querySelectorAll(".Inexplorer");

const scanStatusEl = document.getElementById("scan-status");
const scanProgressBar = document.getElementById("scan-progress-bar");

const planStatusEl = document.getElementById("plan-status");

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

function updateStepUI(stepNumber) {
    // 1. Update Sidebar Links
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    document.getElementById(`step-${stepNumber}-indicator`).classList.add('active');
    
    // 2. Dim inactive cards, highlight active one
    document.querySelectorAll('.glass-card').forEach((card, index) => {
        if (index + 1 === stepNumber) {
            card.style.opacity = "1";
            card.style.transform = "scale(1)";
            card.style.border = "1px solid var(--accent)";
        } else {
            card.style.opacity = "0.4";
            card.style.transform = "scale(0.98)";
            card.style.border = "1px solid var(--border)";
        }
    });

    // 3. Smooth scroll
    document.getElementById(`section-${stepNumber}`).scrollIntoView({ 
        behavior: 'smooth', 
        block: 'center' 
    });
}

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


    // UI Visual Logic
    startScanBtn.disabled = false;
    document.querySelector('.drop-zone').style.borderColor = 'var(--success)';

    updateStepUI(1); // Highlights Step 1 in the sidebar
    
    // Optional: Auto-focus the next button to guide the user
    startScanBtn.classList.add("pulse-animation");
  }
});

// 2) Lancer Scan
startScanBtn.addEventListener("click", async () => {
  if (!selectedPath) return;

  // Visual feedback: Move to step 2 immediately
  updateStepUI(2);
  startScanBtn.classList.remove("pulse-animation");
  // Optional: Auto-focus the next button to guide the user
  buildPlanBtn.classList.add("pulse-animation");

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

  // 1. UI Feedback: Disable buttons and show loading state
  buildPlanBtn.disabled = true;
  applyPlanBtn.disabled = true;
  undoApplyBtn.disabled = true;

  planStatusEl.textContent = "Plan: Analyzing file structure...";
  const treeContainer = document.getElementById("plan-preview-tree");
  treeContainer.innerHTML = '<div class="loading-state">Generating optimal path suggestions...</div>';

  try {
    // 2. Request plan generation from the engine
    const { json: planResponse } = await postJson(apiUrl("/plans"), {
      scanId,
      template: "downloads_basic",
    });

    if (!planResponse.ok) {
      planStatusEl.textContent = `Plan Error: ${planResponse.error.message}`;
      buildPlanBtn.disabled = false;
      return;
    }

    planId = planResponse.data.planId;

    // 3. Fetch the actual actions for the generated plan
    const { json: preview } = await getJson(apiUrl(`/plans/${planId}`));

    if (!preview.ok) {
      planStatusEl.textContent = `Preview Error: ${preview.error.message}`;
      buildPlanBtn.disabled = false;
      return;
    }

    // 4. Process data and update the UI
    const actions = preview.data.actions || [];



      // 1. Calculate Stats
      const totalFiles = actions.filter(a => a.type === 'move').length;
      const newFolders = actions.filter(a => a.type === 'mkdir').length;

      // 2. Update Dashboard UI
      document.getElementById("stat-files").textContent = totalFiles;
      document.getElementById("stat-folders").textContent = newFolders;
      document.getElementById("stat-size").textContent = "Verified"; // Or use size logic if available

      // 3. Show the dashboard
      const dashboard = document.getElementById("plan-stats-summary");
      dashboard.hidden = false;
      dashboard.style.display = "grid";

    // Calculate counts for the status header
    const counts = actions.reduce((acc, a) => {
      acc[a.type] = (acc[a.type] || 0) + 1;
      return acc;
    }, { move: 0, mkdir: 0 });

    // 5. Render the beautiful tree view
    renderFileTree(actions);

    // 6. Update Status text and step indicator
    planStatusEl.textContent = `Plan generated successfully`;
    
    // Update the sidebar/stepper to Step 3
    updateStepUI(3);
    buildPlanBtn.classList.remove("pulse-animation");
    // Optional: Auto-focus the next button to guide the user
    applyPlanBtn.classList.add("pulse-animation");

    // 7. Enable next steps
    applyPlanBtn.disabled = false;
    applyWarningEl.hidden = false;

  } catch (error) {
    console.error("Failed to build plan:", error);
    planStatusEl.textContent = "Plan Error: Connection to engine failed.";
    buildPlanBtn.disabled = false;
  }
});

/**
 * Helper function to render the JSON actions as a beautiful file tree
 */
function renderFileTree(actions) {
  const treeContainer = document.getElementById("plan-preview-tree");
  const countBadge = document.getElementById("file-count-badge");
  
  treeContainer.innerHTML = ""; 
  countBadge.textContent = `${actions.length} Operations`;

  if (actions.length === 0) {
    treeContainer.innerHTML = '<div class="empty-state">Your files are already organized!</div>';
    return;
  }

  actions.forEach(action => {
    const item = document.createElement("div");
    // Assign classes for styling based on action type
    item.className = `tree-item ${action.type === 'move' ? 'action-move' : 'action-mkdir'}`;

    let icon = action.type === 'move' ? '📦' : '📁';
    let content = "";

    if (action.type === 'move') {
      // Get just the filename from the source path for cleaner look
      const fileName = action.from.split(/[\\/]/).pop();
      content = `
        <span class="action-icon">${icon}</span>
        <div class="tree-details">
          <span class="tree-path-orig">${fileName}</span>
          <span class="arrow-icon"> will be moved to </span>
          <span class="tree-path-dest">${action.to}</span>
        </div>
      `;
    } else {
      content = `
        <span class="action-icon">${icon}</span>
        <div class="tree-details">
          <span class="tree-path-dest">New Directory <strong>${action.name}</strong> will be created !</span>
        </div>
      `;
    }

    item.innerHTML = content;
    treeContainer.appendChild(item);
  });
}

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

  // Update the sidebar/stepper to Step 3
    updateStepUI(4);
    applyPlanBtn.classList.remove("pulse-animation");
    // Optional: Auto-focus the next button to guide the user
    undoPlanBtn.classList.add("pulse-animation");
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

  /* if (d.status === "done") {
    clearInterval(applyPollTimer);
    applyStatusEl.textContent = `Apply : done (${applyId})`;
    applyPlanBtn.disabled = false;
  } */

  // Add this inside pollApply where d.status === "done"
  if (d.status === "done") {
    clearInterval(applyPollTimer);
    showSuccessEffect(d.summary.moved, d.summary.createdFolders);
  }
}


function showSuccessEffect(moved, createdFolders) {
    const overlay = document.getElementById("success-overlay");
    document.getElementById("final-files").textContent = moved;
    document.getElementById("final-folders").textContent = createdFolders;
    
    overlay.classList.remove("hidden");
    setTimeout(() => overlay.classList.add("visible"), 100);

    // Simple Confetti Logic
    triggerConfetti();
}

function triggerConfetti() {
    const canvas = document.getElementById("confetti-canvas");
    const ctx = canvas.getContext("2d");
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    let particles = Array.from({ length: 100 }, () => ({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height - canvas.height,
        size: Math.random() * 8 + 4,
        color: Math.random() > 0.5 ? '#3b82f6' : '#10b981',
        speed: Math.random() * 3 + 2
    }));

    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach(p => {
            ctx.fillStyle = p.color;
            ctx.fillRect(p.x, p.y, p.size, p.size);
            p.y += p.speed;
        });
        if (particles[0].y < canvas.height) requestAnimationFrame(draw);
    }
    draw();
}

// Close button logic
document.getElementById("close-success").addEventListener("click", () => {
    document.getElementById("success-overlay").classList.remove("visible");
    setTimeout(() => {
        document.getElementById("success-overlay").classList.add("hidden");
    }, 800);
});


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

InexplorerBtns.forEach(btn => {
  btn.addEventListener("click", async () => {
    if (!selectedPath) return;
    try {
      console.log("Opening folder:", selectedPath);
      await openPath(selectedPath); // folder path is OK
    } catch (e) {
console.error("openPath failed:", e);
}
});
});