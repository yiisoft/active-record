# Yii Active Record Change Log

## 1.0.3 under development

- Bug #558: Fix `SoftDelete` with initiated custom date (@Tigrov)
- Enh #564: Clarify `$relations` parameter type in `JoinWith::__construct()` from `array<string|Closure>` to
  `array<string|callable(ActiveQueryInterface):void>` (@vjik)
- Bug #567: Fix properties with hooks (@Tigrov)
- Bug #562: Fix `ActiveRecordInterface::upsert()` to prioritize passed associative values during updates (@Tigrov)
- Bug #561: Fix `ActiveRecordInterface::upsert()` with `$updateProperties = false` (@Tigrov)
- Bug #550: Relation query should be created by related class, not primary model class (@batyrmastyr)
- Enh #571: Optimize performance of `ActiveRecord::get()` method (@Tigrov)
- Enh #576: Add default config for `yiisoft/config` plugin (@Tigrov)
- Enh #575: Remove check for empty string in `AbstractActiveRecord::markPropertyChanged()` method (@Tigrov)

## 1.0.2 March 11, 2026

- Bug #544: Revert changes from #538 related to create model without a constructor (@Tigrov)

## 1.0.1 February 28, 2026

- Enh #532, #533: Remove unnecessary files from Composer package (@mspirkov)
- Enh #538: It is now possible to instantiate AR model with constructor (@Tigrov, @olegbaturin)
- Bug #527: Fix PHPDoc tags `@see` (@mspirkov)
- Bug #538: Remove `Closure` type from parameter `$modelClass` of `EventsTrait::query()` method (@Tigrov)

## 1.0.0 December 09, 2025

- Initial release.
