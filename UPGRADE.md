# Upgrade guide

## From 1.1.* to 2.0.0

### Renamed package and namespace

The package moved from `digital-craftsman/deserializing-connection` to `buron-digital/deserializing-connection` and the namespace from `DigitalCraftsman\DeserializingConnection` to `BuronDigital\DeserializingConnection`. Apart from the names, nothing changed, so the upgrade is a plain search and replace.

This version depends on `buron-digital/self-aware-normalizers` instead of `digital-craftsman/self-aware-normalizers`. Switch it in the same step (see its upgrade guide) and replace its namespace as well.

1. Replace the package in your `composer.json`.

   Before:
   ```json
   "digital-craftsman/deserializing-connection": "^1.1"
   ```

   After:
   ```json
   "buron-digital/deserializing-connection": "^2.0.0"
   ```

2. Replace the namespace in your whole project – not only in `src` and `tests`, but also in `config` (e.g. `config/bundles.php` and service or Doctrine configuration), migrations, templates and all other files that contain class names. Search for `DigitalCraftsman\DeserializingConnection\` and replace it with `BuronDigital\DeserializingConnection\` (e.g. with "Replace in Files" of your IDE) or run the following command in the root of your project. It also covers escaped class names like `'DigitalCraftsman\\DeserializingConnection\\…'` in strings:

   ```shell
   grep -rlE 'DigitalCraftsman\\+DeserializingConnection' --exclude-dir=vendor --exclude-dir=var --exclude-dir=node_modules --exclude-dir=.git . \
     | xargs perl -pi -e 's/DigitalCraftsman(\\+)DeserializingConnection/BuronDigital$1DeserializingConnection/g'
   ```

   When you switch all packages (`self-aware-normalizers`, `ids`, `date-time-precision`, `deserializing-connection`, `cqs-routing`) at once, you can replace `DigitalCraftsman` with `BuronDigital` instead.

3. Update the packages and clear the cache:

   ```shell
   composer update "buron-digital/*"
   bin/console cache:clear
   ```

4. Check where class names are stored outside of your code. For example, PHP-serialized messages in Symfony Messenger queues or objects in persistent cache pools still contain the previous class names. Let the queues run empty and clear those pools when deploying the change.

## From 0.9.* to 1.0.0

### Dropped support for PHP 8.4

Update to at least PHP 8.5.

### Results of read methods must be used

All methods of the `DeserializingConnection` (`getOne`, `findOne`, `getOneFromSingleValue`, `findOneFromSingleValue`, `findArray`, `findGenerator`) and all `fetch*` methods of the `DecodingConnection` are marked with `#[\NoDiscard]`. When the result of such a call isn't used, PHP emits a warning (which is converted into an exception by the Symfony error handler in debug mode).

If you've used `getOne` or `getOneFromSingleValue` only to check whether a row exists, use `DecodingConnection->fetchBool` with an `EXISTS` query instead. Otherwise use the result or – when you really don't need it – cast the call to `(void)`:

```php
(void) $this->deserializingConnection->findOne(...);
```

## From 0.8.* to 0.9.0

Nothing to do.

## From 0.7.* to 0.8.0

### Breaking change: Keys of `decoderTypes` must not contain dots anymore

The keys of `decoderTypes` now support nested values with the same syntax as the result transformers (e.g. `projects.*.timeEntries.*.hours`). Therefore, a dot (`.`) in a key is interpreted as a separator between levels. A key like `project.name` no longer targets a column named `project.name`, but the value `name` within the column `project`. Additionally, keys must not start or end with `*` anymore.

If you're using column names containing dots, rename them in your SQL (e.g. `SELECT name AS "projectName"` instead of `SELECT name AS "project.name"`) and adapt the keys of `decoderTypes` accordingly.

### Optional: Replace result transformers that only decode values

Result transformers that only convert types of nested values can be replaced with nested decoder types. So instead of:

```php
resultTransformers: [
    ResultTransformer::toTransform(
        key: 'projects.*.timeEntries.*.hours',
        denormalizeResultToClass: null,
        transformer: static fn (int | float | null $hours) => $hours !== null
            ? (float) $hours
            : null,
        isTransformedResultNormalized: false,
    ),
],
```

You can use:

```php
decoderTypes: [
    'projects' => DecoderType::JSON,
    'projects.*.timeEntries.*.hours' => DecoderType::NULLABLE_FLOAT,
],
```

## From 0.6.* to 0.7.0

### Dropped support for PHP 8.3

Update to at least PHP 8.4.

### Dropped support for Symfony 7.3 and below

Update to at least the LTS version 7.4.


## From 0.5.* to 0.6.0

Nothing to do.

## From 0.4.* to 0.5.0

Nothing to do.

## From 0.3.* to 0.4.0

Nothing to do.

## From 0.2.* to 0.3.0

### Replace normalizers and doctrine types

The own normalizers and doctrine types have been replaced with the [`digital-craftsman/self-aware-normalizers`](https://github.com/digital-craftsman-de/self-aware-normalizers) package. The structure and logic is identical at this point, so you just need to adapt the namespaces via search / replace:

Before:

```php
use DigitalCraftsman\DeserializingConnection\Serializer\ArrayNormalizable;
```

After:

```php
use DigitalCraftsman\SelfAwareNormalizers\Serializer\ArrayNormalizable;
```

## From 0.1.* to 0.2.0

### Rename parameter

Change name of parameter for `denormalize` method to `$data`.

Before:

```php
public static function denormalize(array $array): self
{
    return new self($array);
}
```

After:

```php
public static function denormalize(array $data): self
{
    return new self($data);
}
```
