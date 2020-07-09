<?php

namespace Nuwave\Lighthouse\WhereConditions;

use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Schema\AST\PartialParser;

class WhereConditionsServiceProvider extends ServiceProvider
{
    public const DEFAULT_HAS_AMOUNT = 1;

    public const DEFAULT_WHERE_CONDITIONS = 'WhereConditions';

    public const DEFAULT_WHERE_RELATION_CONDITIONS = 'Relation';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Operator::class, SQLOperator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(
            RegisterDirectiveNamespaces::class,
            function (RegisterDirectiveNamespaces $_): string {
                return __NAMESPACE__;
            }
        );

        $dispatcher->listen(
            ManipulateAST::class,
            function (ManipulateAST $manipulateAST): void {
                /** @var \Nuwave\Lighthouse\WhereConditions\Operator $operator */
                $operator = $this->app->make(Operator::class);

                $manipulateAST->documentAST
                    ->setTypeDefinition(
                        static::createWhereConditionsInputType(
                            static::DEFAULT_WHERE_CONDITIONS,
                            'Dynamic WHERE conditions for queries.',
                            'String'
                        )
                    )
                    ->setTypeDefinition(
                        static::createHasConditionsInputType(
                            static::DEFAULT_WHERE_CONDITIONS,
                            'Dynamic HAS conditions for WHERE condition queries.'
                        )
                    )
                    ->setTypeDefinition(
                        PartialParser::enumTypeDefinition(
                            $operator->enumDefinition()
                        )
                    )
                    ->setTypeDefinition(
                        PartialParser::scalarTypeDefinition(/** @lang GraphQL */ '
                            scalar Mixed @scalar(class: "MLL\\\GraphQLScalars\\\Mixed")
                        ')
                    );
            }
        );
    }

    public static function createWhereConditionsInputType(string $name, string $description, string $columnType): InputObjectTypeDefinitionNode
    {
        $hasRelationInputName = $name.self::DEFAULT_WHERE_RELATION_CONDITIONS;

        /** @var \Nuwave\Lighthouse\WhereConditions\Operator $operator */
        $operator = app(Operator::class);

        $operatorName = self::operatorName($operator);
        $operatorDefault = $operator->default();

        return PartialParser::inputObjectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "$description"
            input $name {
                "The column that is used for the condition."
                column: $columnType

                "The operator that is used for the condition."
                operator: $operatorName = $operatorDefault

                "The value that is used for the condition."
                value: Mixed

                "A set of conditions that requires all conditions to match."
                AND: [$name!]

                "A set of conditions that requires at least one condition to match."
                OR: [$name!]

                "Check whether a relation exists, or if it matches a count criteria. And with option for a where clause."
                HAS: $hasRelationInputName
            }
GRAPHQL
        );
    }

    public static function createHasConditionsInputType(string $name, string $description): InputObjectTypeDefinitionNode
    {
        $hasRelationInputName = $name.self::DEFAULT_WHERE_RELATION_CONDITIONS;
        $defaultHasAmount = self::DEFAULT_HAS_AMOUNT;

        /** @var \Nuwave\Lighthouse\WhereConditions\Operator $operator */
        $operator = app(Operator::class);
        $operatorName = self::operatorName($operator);
        $operatorDefault = $operator->defaultHasOperator();

        return PartialParser::inputObjectTypeDefinition(/** @lang GraphQL */ <<<GRAPHQL
            "$description"
            input $hasRelationInputName {
                "The relation that is checked."
                relation: String!

                "The comparision operator to test against the amount."
                operator: $operatorName = $operatorDefault

                "The amount to test."
                amount: Int = $defaultHasAmount

                "Additional condition logic."
                condition: $name
            }
GRAPHQL
        );
    }

    protected static function operatorName(Operator $operator): string
    {
        return PartialParser
            ::enumTypeDefinition(
                $operator->enumDefinition()
            )
            ->name
            ->value;
    }
}
