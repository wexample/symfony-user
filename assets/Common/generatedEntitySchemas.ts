import account from '../data/entity/account.json';

type EntitySchema = { name: string };

export default function getGeneratedEntitySchemas(): Record<string, EntitySchema> {
  return {
    [account.name]: account,
  };
}
