
# HESTIA — API locale (Contrat v1)

Base URL (DEV): http://127.0.0.1:8787
Content-Type: application/json

## Convention de réponse (STANDARD)
Toutes les réponses doivent suivre ce format :

### Succès
{
  "ok": true,
  "data": <payload>,
  "error": null
}

### Erreur
{
  "ok": false,
  "data": null,
  "error": {
    "code": "SOME_CODE",
    "message": "Message lisible",
    "details": <optionnel>
  }
}

---

## 0) Healthcheck
### GET /health
Réponse:
{
  "ok": true,
  "data": { "status": "ok", "name": "HESTIA Engine", "version": "0.1.0" },
  "error": null
}

---

## 1) Lancer un scan
### POST /v1/scans
Body:
{
  "path": "C:\\Users\\toi\\Downloads",
  "options": {
    "followSymlinks": false,
    "maxDepth": 20,
    "exclude": [
      ".git",
      "node_modules",
      "vendor"
    ]
  }
}

Réponse 201:
{
  "ok": true,
  "data": {
    "scanId": "scn_...",
    "status": "queued"
  },
  "error": null
}

---

## 2) Voir l’état d’un scan
### GET /v1/scans/{scanId}
Réponse 200:
{
  "ok": true,
  "data": {
    "scanId": "scn_...",
    "status": "running",
    "progress": {
      "filesDiscovered": 1200,
      "filesIndexed": 650,
      "percent": 54
    },
    "summary": {
      "byExtension": {
        "pdf": 23,
        "jpg": 120,
        "zip": 4
      }
    },
    "warnings": [
      { "code": "SKIPPED_PERMISSION", "message": "Accès refusé sur ..."}
    ]
  },
  "error": null
}

---

## 3) Construire un plan (prévisualisation)
### POST /v1/plans
Body:
{
  "scanId": "scn_...",
  "template": "downloads_basic",
  "options": {
    "rename": {
      "enabled": true,
      "pattern": "{date}-{title}",
      "sanitize": true
    },
    "quarantineUncertain": true
  }
}

Réponse 201:
{
  "ok": true,
  "data": {
    "planId": "pln_...",
    "stats": {
      "moveCount": 900,
      "renameCount": 420,
      "mkdirCount": 35,
      "uncertainCount": 18
    }
  },
  "error": null
}

---

## 4) Prévisualiser un plan
### GET /v1/plans/{planId}
Réponse 200 (extrait):
{
  "ok": true,
  "data": {
    "planId": "pln_...",
    "root": "C:\\Users\\toi\\Downloads",
    "actions": [
      {
        "type": "mkdir",
        "to": "C:\\Users\\toi\\Downloads\\Images\\2026\\01"
      },
      {
        "type": "move",
        "from": "C:\\Users\\toi\\Downloads\\IMG_1234.jpg",
        "to": "C:\\Users\\toi\\Downloads\\Images\\2026\\01\\IMG_1234.jpg"
      },
      {
        "type": "rename",
        "from": "C:\\Users\\toi\\Downloads\\facture.pdf",
        "to": "C:\\Users\\toi\\Downloads\\Administratif\\Factures\\2026-01-04-facture.pdf"
      }
    ]
  },
  "error": null
}

---

## 5) Appliquer un plan
### POST /v1/applies
Body:
{
  "planId": "pln_..."
}

Réponse 201:
{
  "ok": true,
  "data": {
    "applyId": "app_...",
    "status": "running"
  },
  "error": null
}

---

## 6) Voir l’état d’une application
### GET /v1/applies/{applyId}
Réponse 200:
{
  "ok": true,
  "data": {
    "applyId": "app_...",
    "status": "done",
    "summary": {
      "moved": 900,
      "renamed": 420,
      "createdFolders": 35,
      "errors": 0
    }
  },
  "error": null
}

---

## 7) Annuler (undo)
### POST /v1/undo
Body:
{
  "applyId": "app_..."
}

Réponse 200:
{
  "ok": true,
  "data": { "status": "rolled_back" },
  "error": null
}
