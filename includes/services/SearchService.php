<?php
declare(strict_types=1);

/**
 * Facade service for Catalog, Folder, Shop Product & Reader In-Book Search.
 */
final class SearchService
{
    public static function queryItems(array $scope, array $filters): array
    {
        return browse_query_items($scope, $filters);
    }

    public static function queryProducts(array $params = []): array
    {
        return shop_query_products($params);
    }

    public static function didYouMean(string $query): ?string
    {
        return find_did_you_mean($query);
    }

    public static function logSearch(string $query, int $resultCount): void
    {
        log_search_query($query);
        if ($resultCount === 0) {
            log_search_miss($query);
        }
    }
}
