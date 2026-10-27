import user from '../data/entity/user.json';

type EntitySchema = { name: string };

export default function getGeneratedEntitySchemas(): Record<string, EntitySchema> {
  return {
    [user.name]: user,
  };
}
