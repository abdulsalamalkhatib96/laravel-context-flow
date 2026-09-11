# Architecture

```text
Laravel Context
     |
     v
ContextFlowManager
     |
     +-- ContextRegistry
     +-- PropagationPolicy
     +-- TrustResolver
     +-- SensitiveKeyDetector
     |
     +--> HTTP inbound/outbound
     +--> Laravel native queue dehydration/hydration
     +--> Console / Scheduler boundaries
```

## Identity semantics

- Correlation ID: stable for a business workflow.
- Execution ID: unique for each process/boundary execution.
- Causation ID: previous execution that caused the current one.
- Request ID: unique to one HTTP request.

## HTTP receive

```text
headers -> signature/trust -> validation -> baggage allowlist -> new execution/request IDs -> Laravel Context
```

## HTTP send

```text
Laravel Context -> registry/policy -> destination trust -> size/sensitivity filter -> headers
```

## Queue

The package subscribes to Laravel Context dehydration and mutates Laravel's cloned dehydration repository, not the live request repository. On hydration it rotates the execution ID. Job metadata is added on `JobProcessing`.

## Why no second context store

A second static or singleton store would duplicate Laravel, break Octane/worker safety, and create disagreement over which context is authoritative. The package therefore stores execution values only in Laravel Context.
