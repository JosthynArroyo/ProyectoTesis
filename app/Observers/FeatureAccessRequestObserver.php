<?php

namespace App\Observers;

use App\Models\FeatureAccessRequest;
use App\Services\LayoutMetricsService;

class FeatureAccessRequestObserver
{
    public function saved(FeatureAccessRequest $featureAccessRequest): void
    {
        $this->invalidateCaches($featureAccessRequest);
    }

    public function deleted(FeatureAccessRequest $featureAccessRequest): void
    {
        $this->invalidateCaches($featureAccessRequest);
    }

    private function invalidateCaches(FeatureAccessRequest $featureAccessRequest): void
    {
        $layoutMetrics = app(LayoutMetricsService::class);
        $layoutMetrics->forgetFeatureStatus((int) $featureAccessRequest->user_id, $featureAccessRequest->feature);

        if ($featureAccessRequest->feature === 'personalizacion') {
            $layoutMetrics->forgetPendingFeatureRequests('personalizacion');
        }
    }
}
