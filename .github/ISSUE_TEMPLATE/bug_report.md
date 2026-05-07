---
name: Bug report
about: Report a defect in ci4-api-core
title: '[Bug] '
labels: bug
assignees: ''
---

## Summary

<!-- One sentence describing the bug. -->

## Environment

- PHP version: <!-- e.g. 8.2.30 -->
- CodeIgniter version (consumer project): <!-- e.g. 4.7.x — check composer.lock in the consuming app -->
- crud-maker version: <!-- composer show dcardenasl/ci4-api-core -->
- Consumer project: <!-- ci4-api-starter / a derivative — share its CLAUDE.md if non-public -->

## Reproduction

The exact `make:crud` invocation that triggers the issue:

```bash
bash vendor/bin/make-crud.sh ResourceName Domain '...' yes
```

Or, for `make:crud:remove` / `module:check` issues, the exact command:

```bash
php spark module:check Resource --domain Domain
```

## Expected behavior

<!-- What should happen. Reference docs/ARCHITECTURE_CONTRACT.md if applicable. -->

## Actual behavior

<!-- Output excerpts. Generated file content if relevant — feel free to attach a small fixture. -->

## Generator artifact involved (if known)

- [ ] DtoGenerator
- [ ] ControllerGenerator
- [ ] ServiceGenerator
- [ ] MigrationGenerator
- [ ] RouteGenerator
- [ ] ModelEntityGenerator
- [ ] LanguageGenerator
- [ ] TestGenerator
- [ ] ConfigWireman (Services.php injection)
- [ ] Field parser / Type mapper

## Additional context

- Custom `App\Config\Scaffolding` overrides (paths, base classes, filters)?
- Stack trace if applicable:
  ```
  ```
