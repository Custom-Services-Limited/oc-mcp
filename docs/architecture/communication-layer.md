# Communication Layer Diagrams

These diagrams explain the current MCP communication path implemented by the OpenCart extension. They complement the high-level [architecture overview](../architecture.md) and the [tool catalog](../tools.md).

Relevant runtime files:

- [`ProtocolServer`](../../src/shared/system/library/mcp/protocol_server.php) handles JSON-RPC, authentication, policy checks, dispatch, responses, and audit writes.
- [`ToolRegistry`](../../src/shared/system/library/mcp/tool_registry.php) defines tools and filters them per client.
- [`SchemaValidator`](../../src/shared/system/library/mcp/schema_validator.php) validates tool arguments before execution.
- [`ToolExecutor`](../../src/shared/system/library/mcp/tools.php) calls the OpenCart-facing operations.
- [`Repository`](../../src/shared/system/library/mcp/repository.php) owns configuration, client lookup, rate limits, confirmations, idempotency, audit logging, and OpenCart database access.

## Communication Context

```mermaid
flowchart LR
    Client["Approved MCP client<br/>AI assistant or automation"] -->|"HTTPS POST<br/>Streamable HTTP JSON-RPC<br/>Authorization: Bearer token"| Route["OpenCart catalog route<br/>index.php?route=extension/mcp/server"]

    Route --> OC3["OpenCart 3 catalog controller<br/>src/opencart3/upload/catalog/controller/extension/mcp/server.php"]
    Route --> OC4["OpenCart 4 catalog controller<br/>src/opencart4/catalog/controller/server.php"]

    OC3 -->|"raw body, server vars,<br/>headers, query params"| Protocol["OpenCartMcp\\ProtocolServer"]
    OC4 -->|"raw body, server vars,<br/>headers, query params"| Protocol

    Protocol --> Registry["ToolRegistry<br/>tool metadata and client filtering"]
    Protocol --> Validator["SchemaValidator<br/>input schema checks"]
    Protocol --> Executor["ToolExecutor<br/>tool implementation dispatch"]
    Protocol --> Repository["Repository<br/>settings, clients, audit, limits"]

    Repository --> McpTables["MCP tables<br/>mcp_client<br/>mcp_audit_log<br/>mcp_rate_limit<br/>mcp_confirmation<br/>mcp_idempotency<br/>mcp_cart_session"]
    Repository --> OpenCartTables["OpenCart tables and services<br/>catalog, cart, customer, admin data"]
    Executor --> Repository

    Protocol -->|"JSON-RPC result or error<br/>with request_id"| Client
```

The OpenCart 3 and OpenCart 4 controllers are thin adapters. Shared protocol behavior stays in `src/shared/system/library/mcp/`.

## Request Lifecycle

```mermaid
flowchart TD
    Start["Catalog endpoint receives request"] --> Options{"REQUEST_METHOD is OPTIONS?"}
    Options -->|"yes"| Cors["Return CORS preflight response"]
    Options -->|"no"| Post{"REQUEST_METHOD is POST?"}
    Post -->|"no"| MethodError["Return 405 POST required"]
    Post -->|"yes"| Handle["ProtocolServer::handle"]

    Handle --> QueryTokens{"Query contains token,<br/>access_token, bearer_token,<br/>or authorization?"}
    QueryTokens -->|"yes"| TokenQueryError["TOKEN_IN_QUERY_REJECTED"]
    QueryTokens -->|"no"| BodySize{"Body within<br/>module_mcp_max_body_bytes?"}
    BodySize -->|"no"| TooLarge["REQUEST_TOO_LARGE"]
    BodySize -->|"yes"| ParseJson{"Valid JSON object<br/>with method?"}
    ParseJson -->|"no"| InvalidRequest["INVALID_JSON or INVALID_REQUEST"]
    ParseJson -->|"yes"| Enabled{"Server enabled?"}
    Enabled -->|"no"| Disabled["SERVER_DISABLED"]
    Enabled -->|"yes"| Auth{"Bearer token valid,<br/>active, not expired?"}
    Auth -->|"no"| AuthError["AUTHENTICATION_FAILED"]
    Auth -->|"yes"| Policy{"Client IP and Origin<br/>allowed?"}
    Policy -->|"no"| PolicyError["IP_NOT_ALLOWED or ORIGIN_NOT_ALLOWED"]
    Policy -->|"yes"| Rate{"Client within<br/>rate limit?"}
    Rate -->|"no"| RateError["RATE_LIMITED"]
    Rate -->|"yes"| Dispatch["Dispatch MCP method"]

    Dispatch --> Result["Build JSON-RPC success response"]
    TokenQueryError --> Error["Build JSON-RPC error response"]
    TooLarge --> Error
    InvalidRequest --> Error
    Disabled --> Error
    AuthError --> Error
    PolicyError --> Error
    RateError --> Error

    Result --> Audit["Write redacted audit row"]
    Error --> Audit
    Audit --> Response["Return response with request_id"]
```

Every handled JSON-RPC request receives a generated `request_id`. Audit logging records redacted input, result or error summary, client context, method, tool, risk tier, policy results, and duration.

## Tool Dispatch

```mermaid
flowchart TD
    Dispatch["ProtocolServer::dispatch"] --> Method{"JSON-RPC method"}

    Method -->|"initialize"| Initialize["Return serverInfo, capabilities,<br/>transport, and client metadata"]
    Method -->|"tools/list"| List["ToolRegistry::listResponse"]
    Method -->|"tools/call"| Call["ProtocolServer::callTool"]
    Method -->|"resources/list"| Resources["Return scoped resource list"]
    Method -->|"resources/read"| ReadResource["Read allowed resource"]
    Method -->|"prompts/list"| Prompts["Return scoped prompt list"]
    Method -->|"prompts/get"| PromptGet["Return allowed prompt"]
    Method -->|"other"| Unsupported["UNSUPPORTED_METHOD"]

    List --> Available["ToolRegistry::availableForClient"]
    Available --> Packs["Capability pack check"]
    Packs --> Scopes["Required scopes check"]
    Scopes --> AllowedTools["Optional allowed_tools check"]
    AllowedTools --> ToolList["Visible tool names, descriptions,<br/>and input schemas"]

    Call --> ToolName{"Tool name present<br/>and registered?"}
    ToolName -->|"no"| ToolError["INVALID_REQUEST or TOOL_NOT_FOUND"]
    ToolName -->|"yes"| Allowed{"Tool available<br/>for client?"}
    Allowed -->|"no missing scope"| ScopeError["SCOPE_MISSING"]
    Allowed -->|"no disabled for client"| DisabledTool["TOOL_DISABLED"]
    Allowed -->|"yes"| Schema["SchemaValidator::validate"]
    Schema --> ValidSchema{"Arguments valid?"}
    ValidSchema -->|"no"| SchemaError["INVALID_INPUT_SCHEMA"]
    ValidSchema -->|"yes"| Write{"Tool is write?"}
    Write -->|"no"| Execute["ToolExecutor::execute"]
    Write -->|"yes"| WriteGuardrails["Write guardrails flow"]
    Execute --> ToolResult["JSON content result"]
```

Tool exposure is explicit. Clients only see and call tools allowed by their capability packs, scopes, and optional tool allowlist.

## Write Tool Guardrails

```mermaid
sequenceDiagram
    autonumber
    participant Client as MCP client
    participant Protocol as ProtocolServer
    participant Registry as ToolRegistry
    participant Validator as SchemaValidator
    participant Repo as Repository
    participant Executor as ToolExecutor
    participant DB as OpenCart and MCP tables

    Client->>Protocol: tools/call with write tool and dry_run=true
    Protocol->>Registry: find tool and confirm client access
    Protocol->>Validator: validate input schema
    Protocol->>Repo: clientHasOpenCartModifyPermission
    Protocol->>Executor: execute dry run
    Executor->>DB: read current state and build proposed diff
    DB-->>Executor: current data
    Executor-->>Protocol: dry-run result with before/after diff
    Protocol->>Repo: createConfirmation(input hash, entity, tool)
    Repo->>DB: insert mcp_confirmation
    Protocol-->>Client: dry-run result plus confirmation_token

    Client->>Protocol: tools/call with confirmation_token, idempotency_key, reason
    Protocol->>Registry: confirm same tool is still allowed
    Protocol->>Validator: validate execution input
    Protocol->>Repo: check idempotency key
    alt Existing key with same input
        Repo-->>Protocol: saved result_summary
        Protocol-->>Client: replay saved result
    else Existing key with different input
        Repo-->>Protocol: conflict
        Protocol-->>Client: IDEMPOTENCY_CONFLICT
    else New idempotency key
        Protocol->>Repo: verify confirmation token and input hash
        Repo->>DB: read mcp_confirmation
        Repo-->>Protocol: confirmation accepted
        Protocol->>Executor: execute write
        Executor->>DB: apply OpenCart mutation
        DB-->>Executor: mutation result
        Executor-->>Protocol: final result
        Protocol->>Repo: saveIdempotency(result summary)
        Repo->>DB: insert mcp_idempotency
        Protocol-->>Client: final JSON content result
    end

    Protocol->>Repo: audit success or error
    Repo->>DB: insert mcp_audit_log
```

Write tools require the same protocol gate as read tools, plus OpenCart MCP modify permission for the client creator user group. Mutating execution is separated from dry-run preview by confirmation and idempotency checks.
