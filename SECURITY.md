# Security policy

## Reporting a vulnerability

Please do not open a public issue for a security problem.

- GitHub: use **Report a vulnerability** on the repository's Security tab (private advisory).
- GitLab: open a **confidential** issue.

Include the affected version or commit, the steps to reproduce on a local test install and the impact you
observed. Do not test against servers or sites you do not own.

## Supported versions

Only the latest commit on the `main` branch receives security fixes while VibeCraft is in development.

## Deployment hardening

See [docs/SECURITY.md](docs/SECURITY.md) for the production checklist: HTTPS, document root, proxy
settings, cookie flags, cron and bridge key handling.
