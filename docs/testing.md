# Testing strategy

Critical regression areas:

- public input cannot inject restricted tenant/actor metadata;
- invalid/expired signatures are treated as public;
- external HTTP receives no Context Flow headers under default policy;
- internal HTTP receives only registered allowed keys;
- queue dehydration removes registered local-only keys and sensitive-looking keys;
- each job attempt receives a new execution ID while correlation survives;
- request/scheduled boundaries reset package-managed registered state;
- `withoutPropagation()` suppresses both HTTP and queue propagation;
- `only()` limits application metadata while retaining lineage IDs;
- UUIDv7 output remains valid and unique.
