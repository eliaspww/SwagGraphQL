<?php declare(strict_types=1);

namespace SwagGraphQL\Resolver;

use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class AssociationResolver
{
    const TECHNICAL_FIELDS = [
        'edges',
        'node',
        'pageInfo',
        'aggregations',
        'results'
    ];

    /**
     * adds all necessary Associations to the criteria
     * therefore it traverses the fieldSelection-array to get all Associations that should be loaded
     */
    public static function addAssociations(Criteria $criteria, array $fieldSelection, string $definition): void
    {
        foreach ($fieldSelection as $field => $selection) {
            if (is_array($selection)) {
                if (!$definition::getFields()->has($field) && static::isTechnicalField($field)) {
                    static::addAssociations($criteria, $selection, $definition);
                    continue;
                }
                $association = static::getAssociationDefinition($definition, $field);
                $associationCriteria = new Criteria();
                static::addAssociations($associationCriteria, $selection, $association);
                $criteria->addAssociation(sprintf('%s.%s', $definition::getEntityName(), $field), $associationCriteria);
            }
        }
    }

    private static function isTechnicalField(string $field): bool
    {
        return in_array($field, static::TECHNICAL_FIELDS);
    }

    private static function getAssociationDefinition(string $definition,string $association): string
    {
        /** @var FieldCollection $fields */
        $fields = $definition::getFields();
        foreach ($fields as $field) {
            if ($field->getPropertyName() !== $association) {
                continue;
            }

            switch (true) {
                case $field instanceof ManyToManyAssociationField:
                    return $field->getReferenceDefinition();
                case $field instanceof OneToManyAssociationField:
                case $field instanceof ManyToOneAssociationField:
                    return $field->getReferenceClass();
            }
        }

        throw new \Exception(sprintf('Association "%s" on Entity "%s" not found', $association, $definition));
    }
}