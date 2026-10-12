# Security Policy

## Supported versions

| Version | Supported |
| --- | --- |
| 2.x | Yes |
| 1.x | Security fixes until 2027-01-01 |

## Reporting a vulnerability

Please email hello@usamamuneer.me rather than opening a public issue. You should
get an acknowledgement within 72 hours and an assessment within a week.

Include the affected version, a description of the impact, and steps to
reproduce.

## Notes for auditors

Two areas are worth the closest look:

- **Command authorization.** Providers filter on Filament policies, and
  `CommandRegistry::find()` rebuilds and re-authorizes before any action runs.
  The client sends only an opaque id.
- **Result rendering.** Labels are escaped before highlighting is applied.
  Icons are rendered server-side from a name lookup, so icon markup never
  originates from client-controlled data.

## Known change in 2.0

v1 listed every registered page and resource to any authenticated user,
regardless of policy. That disclosed the panel's structure and produced 403s on
selection. Applications on 1.x should upgrade.
