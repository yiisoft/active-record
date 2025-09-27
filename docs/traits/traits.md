# Extending Functionality With Traits

The library provides several traits that can be used to extend the functionality of `ActiveRecord` models.
These traits can be included in your model classes to add specific behaviors or features.

- [ArrayableTrait](arrayable.md) provides `toArray()` method to convert a model to an array format;
- [ArrayAccessTrait](array-access.md) allows accessing model properties and relations using array syntax;
- [ArrayIteratorTrait](array-iterator.md) allows accessing model properties and relations iteratively;
- [CustomConnectionTrait](custom-connection.md) allows using a custom database connection for a model;
- [CustomTableNameTrait](custom-table-name.md) allows using a custom table name for a model;
- [EventsTrait](events.md) allows using events and handlers for a model;
- `FactoryTrait` allows creating models and relations using [yiisoft/factory](https://github.com/yiisoft/factory);
- `MagicPropertiesTrait` stores properties in a private property and provides magic getters
  and setters for accessing the model properties and relations;
- `MagicRelationsTrait` allows using methods with prefix `get` and suffix `Query` to define
  relations (e.g. `getOrdersQuery()` for `orders` relation);
- `PrivateProopertiesTrait` allows using [private properties](../create-model.md#private-properties) 
  in a model;
- `RepositoryTrait` provides methods to interact with a model as a repository.

Back to [README](../../README.md)
