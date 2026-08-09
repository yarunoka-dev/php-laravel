---
title: yarunoka/laravel
description: The Laravel integration of the Yrnk schedule DSL — config-driven evaluation, Eloquent casts, and validation rules.
sidebar:
  order: 1
---

`yarunoka/laravel` binds [yarunoka/core](https://github.com/yarunoka-dev/php-core)
— the PHP implementation of **Yrnk**, the JSON DSL for calendar-aware
schedules — into Laravel. It builds the evaluation environment from your
application's configuration, stores schedules in Eloquent columns with
validation on every path, and rejects a bad request with the engine's own
message.

The language itself is defined in the
[spec repository](https://github.com/yarunoka-dev/spec/tree/1.0), and the
engine — parsing, building, and the questions it answers — is documented
by [yarunoka/core](https://github.com/yarunoka-dev/php-core). This
documentation covers only what the bridge adds on top.

- **Guides** — installing the package, configuring the evaluation
  environment, and using the casts and rules
