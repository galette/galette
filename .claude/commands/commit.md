---
description: Propose a commit message based on staged changes, following the project's commit style. Does not commit automatically — waits for approval.
---

Prepare a commit message for the current changes in `/var/www/html/private/galette.git`.

1. Run `git diff --staged` to see staged changes. If nothing is staged, run `git diff` and `git status` to understand the state, then ask the user which files to stage.

2. Run `git log --oneline -15` to observe the project's commit message style (language, format, tone).

3. Analyse the changes and **propose** a commit message following the observed style:
   - Short subject line (≤ 72 chars)
   - Body if needed (explain *why*, not just *what*)
   - Reference an issue number if the change is clearly linked to one

4. **Do not run `git commit` automatically.** Present the proposed message and wait for the user to validate, adjust, or reject it.

5. Once approved, run:
   ```bash
   git commit -m "$(cat <<'EOF'
   <approved message>
   EOF
   )"
   ```
