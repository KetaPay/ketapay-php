## Summary

<!-- What changed, in a few bullets. -->

## Why

<!-- The problem or ticket this solves. -->

## Test plan

- [ ] Unit tests added or updated for the change
- [ ] `composer format:check && composer lint && composer test && composer test:contract`
- [ ] `composer smoke:package`

## Checklist

- [ ] PR title is a conventional commit (`feat:`, `fix:`, `chore:` ...). Release notes are built from it
- [ ] API change? The spec was changed first in `documentation/openapi/public`, then `composer sync-spec` and `composer generate` here
- [ ] No breaking change to the public API, or the title says `feat!:` and explains it
- [ ] README updated if usage changed
