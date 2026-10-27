import AbstractApiEntity from '@wexample/js-api-entity/Common/AbstractApiEntity';
import schema from '../data/entity/user.json';

export default class User extends AbstractApiEntity {
  static readonly entityName = 'user';

  static retrieveEntitySchema() {
    return schema;
  }
}
