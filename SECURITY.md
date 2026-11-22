# Security Policy

## Reporting a Vulnerability

Please report security issues privately, using the "Report a vulnerability" button of the [Security tab](https://github.com/galette/galette/security) of the repository.

Do not open a public issue or pull request for a vulnerability.

## Supported Versions

Security fixes are released for the latest stable version only.

## How Reports Are Handled

Each report is reviewed by the maintainers, who assess its severity using CVSS. The score proposed in the report is a starting point; the final severity is ours to set.

| Severity        | Handling                                                                                       |
|-----------------|------------------------------------------------------------------------------------------------|
| Critical, High  | Fixed in a release; advisory published with a CVE, a few months after the release.             |
| Medium          | Fixed in a release; advisory published without a CVE, a few weeks after the release.          |
| Low             | Handled as a regular bug: the advisory is closed and the fix ships with the next release.      |

Reporters are credited on published advisories.

Release notes and announcements only describe security fixes in general terms, so that users have time to upgrade before details are disclosed.
