<?php

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;

/**
 * Helpers partagés pour les actions bulk du back-office.
 */
trait BulkSelectionTrait
{
    /**
     * @return list<int>
     */
    private function parseBulkIds(Request $request): array
    {
        $rawIds = $request->request->all('ids');
        if (!\is_array($rawIds)) {
            return [];
        }

        $ids = [];
        foreach ($rawIds as $rawId) {
            $id = filter_var($rawId, \FILTER_VALIDATE_INT);
            if ($id !== false && $id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
