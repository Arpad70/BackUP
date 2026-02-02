#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="$(pwd)"
KEY_REL=".github/deploy_keys/deploy_key"
GIT_REMOTE_SSH="git@github.com:Arpad70/BackUP.git"
WORKFLOW_FILE="deploy.yml"
BRANCH="main"

echo "Working in: $REPO_DIR"

# 1) remove private key from index
if [ -f "$KEY_REL" ]; then
  echo "Removing $KEY_REL from git index..."
  git rm --cached "$KEY_REL" || true
else
  echo "No private key file at $KEY_REL"
fi

# 2) add .gitignore entry
if ! grep -qxF '.github/deploy_keys/' .gitignore 2>/dev/null; then
  echo '.github/deploy_keys/' >> .gitignore
  git add .gitignore
fi

# 3) commit if needed
if git diff --cached --quiet; then
  echo "No staged changes to commit"
else
  git commit -m "Remove deploy private key from repo and ignore deploy_keys"
fi

# 4) ensure remote uses SSH
echo "Setting origin to SSH: $GIT_REMOTE_SSH"
git remote set-url origin "$GIT_REMOTE_SSH" || true

# 5) push via SSH
echo "Pushing to origin/$BRANCH..."
if [ -f "$KEY_REL" ]; then
  GIT_SSH_COMMAND="ssh -i $KEY_REL -o IdentitiesOnly=yes -o StrictHostKeyChecking=no" git push origin "$BRANCH"
else
  git push origin "$BRANCH"
fi

# 6) optionally dispatch workflow if GITHUB_TOKEN provided
if [ -n "${GITHUB_TOKEN:-}" ]; then
  echo "Dispatching workflow $WORKFLOW_FILE on branch $BRANCH via API..."
  curl -s -X POST \
    -H "Authorization: token $GITHUB_TOKEN" \
    -H "Accept: application/vnd.github+json" \
    "https://api.github.com/repos/Arpad70/BackUP/actions/workflows/$WORKFLOW_FILE/dispatches" \
    -d "{\"ref\":\"$BRANCH\"}"
  echo "Workflow dispatch requested."
else
  echo "GITHUB_TOKEN not set — skipping workflow dispatch."
fi

# 7) list recent runs (if GITHUB_TOKEN)
if [ -n "${GITHUB_TOKEN:-}" ]; then
  echo "Listing last 10 runs (API)..."
  curl -s -H "Authorization: token $GITHUB_TOKEN" \
    "https://api.github.com/repos/Arpad70/BackUP/actions/runs?per_page=10" | jq '.workflow_runs[] | {id,name,status,conclusion,event,created_at}'
fi

echo "Done."
