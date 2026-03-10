<?php
declare(strict_types = 1);

namespace Civi\Contract\Api4\Helper;

final class FieldNameHelper {

  /**
   * @return array<string, string>
   *   All field names of the given entity including suffix fields in both key
   *   and value.
   */
  public function getFieldNames(string $entityName): array {
    static $fieldNames = [];

    return $fieldNames[$entityName] ??= $this->doGetFieldNames($entityName);
  }

  /**
   * @return array<string, string>
   */
  private function doGetFieldNames(string $entityName): array {
    $fieldNames = [];
    /** @var \ArrayObject<int, array{name: string, suffixes: null|list<string>}> $fields */
    $fields = civicrm_api4($entityName, 'getFields', [
      'select' => ['name', 'suffixes'],
      'checkPermissions' => FALSE,
    ]);
    foreach ($fields as $field) {
      $fieldNames[$field['name']] = $field['name'];
      foreach ($field['suffixes'] ?? [] as $suffix) {
        $suffixFieldName = $field['name'] . ':' . $suffix;
        $fieldNames[$suffixFieldName] = $suffixFieldName;
      }
    }

    return $fieldNames;
  }

}
