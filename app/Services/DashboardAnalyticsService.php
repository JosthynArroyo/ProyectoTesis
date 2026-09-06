<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\User;
use App\Services\Analytics\AdminDashboardAnalyticsService;
use App\Services\Analytics\DashboardChartBuilder;
use App\Services\Analytics\DashboardPeriodResolver;
use App\Services\Analytics\DoctorDashboardAnalyticsService;
use App\Services\Analytics\LaboratoryDashboardAnalyticsService;
use Carbon\Carbon;

class DashboardAnalyticsService
{
    protected string $timezone;

    public function __construct(
        protected DashboardPeriodResolver $periodResolver,
        protected DashboardChartBuilder $chartBuilder,
        protected AdminDashboardAnalyticsService $adminAnalytics,
        protected DoctorDashboardAnalyticsService $doctorAnalytics,
        protected LaboratoryDashboardAnalyticsService $laboratoryAnalytics,
        ?string $timezone = null
    ) {
        $this->timezone = $timezone ?: config('app.timezone', 'America/Guayaquil');
    }

    public function buildSuperadminDashboard(User $user, array $filters = []): array
    {
        $this->timezone = config('app.timezone', 'America/Guayaquil');

        return $this->adminAnalytics->buildSuperadminDashboard($user, $filters, $this->timezone);
    }

    public function buildAdminDashboard(User $user, array $filters = []): array
    {
        $this->timezone = config('app.timezone', 'America/Guayaquil');

        return $this->adminAnalytics->buildAdminDashboard($user, $filters, $this->timezone);
    }

    public function buildDoctorDashboard(User $user, array $filters = []): array
    {
        $this->timezone = config('app.timezone', 'America/Guayaquil');

        return $this->doctorAnalytics->buildDoctorDashboard($user, $filters, $this->timezone);
    }

    public function buildLaboratorioDashboard(User $user, array $filters = []): array
    {
        $this->timezone = config('app.timezone', 'America/Guayaquil');

        return $this->laboratoryAnalytics->buildLaboratorioDashboard($user, $filters, $this->timezone);
    }

    public function resolveRange(array $filters = [], string $defaultPeriod = 'month', ?Carbon $now = null): array
    {
        $this->timezone = config('app.timezone', 'America/Guayaquil');

        return $this->periodResolver->resolveRange($filters, $defaultPeriod, $now, $this->timezone);
    }

    public function periodOptions(): array
    {
        return $this->periodResolver->periodOptions();
    }

    /*
    |--------------------------------------------------------------------------
    | Protected Compatibility Proxies
    |--------------------------------------------------------------------------
    | Preserves exact internal helper access for tests, extensions or callers.
    */

    protected function getSystemStartDate(Carbon $now): Carbon
    {
        return $this->periodResolver->getSystemStartDate($now, $this->timezone);
    }

    protected function laterDate(Carbon $first, Carbon $second): Carbon
    {
        return $this->periodResolver->laterDate($first, $second);
    }

    protected function earlierDate(Carbon $first, Carbon $second): Carbon
    {
        return $this->periodResolver->earlierDate($first, $second);
    }

    protected function getVisualStartDate(Carbon $systemStart, ?string $minRecordDate): Carbon
    {
        return $this->periodResolver->getVisualStartDate($systemStart, $minRecordDate, $this->timezone);
    }

    protected function getVisualEndDate(?Carbon $rangeEnd, ?string $maxRecordDate, Carbon $now): Carbon
    {
        return $this->periodResolver->getVisualEndDate($rangeEnd, $maxRecordDate, $now, $this->timezone);
    }

    protected function buildAllRange(Carbon $systemStart, Carbon $now): array
    {
        return $this->periodResolver->buildAllRange($systemStart, $now, $this->timezone);
    }

    protected function buildResolvedRange(string $period, Carbon $start, Carbon $end, string $grouping, Carbon $now): array
    {
        return $this->periodResolver->buildResolvedRange($period, $start, $end, $grouping, $now);
    }

    protected function periodLabel(string $period): string
    {
        return $this->periodResolver->periodLabel($period);
    }

    protected function rangeQueryParams(?array $range): array
    {
        return $this->periodResolver->rangeQueryParams($range);
    }

    protected function buildCitaStateLinks(string $routeName, array $range): array
    {
        return $this->periodResolver->buildCitaStateLinks($routeName, $range);
    }

    protected function citasQuery()
    {
        return $this->adminAnalytics->citasQuery();
    }

    protected function laboratoryOrdersQuery()
    {
        return $this->laboratoryAnalytics->laboratoryOrdersQuery();
    }

    protected function countUsersByRoles(array $roles): array
    {
        return $this->adminAnalytics->countUsersByRoles($roles);
    }

    protected function countActiveDoctors(): int
    {
        return $this->adminAnalytics->countActiveDoctors();
    }

    protected function countCitasInRange(?int $doctorId, ?Carbon $start, ?Carbon $end): int
    {
        return $this->adminAnalytics->countCitasInRange($doctorId, $start, $end);
    }

    protected function countCitasByState(?int $doctorId, ?Carbon $start, ?Carbon $end): array
    {
        return $this->adminAnalytics->countCitasByState($doctorId, $start, $end);
    }

    protected function countPendingLabOrders(): int
    {
        return $this->adminAnalytics->countPendingLabOrders();
    }

    protected function countDocumentsInRange(?Carbon $start, ?Carbon $end): array
    {
        return $this->adminAnalytics->countDocumentsInRange($start, $end);
    }

    protected function buildStateSeries($baseQuery, array $states, callable $labelResolver, string $field, ?int $doctorId, ?Carbon $start, ?Carbon $end): array
    {
        return $this->chartBuilder->buildStateSeries($baseQuery, $states, $labelResolver, $field, $doctorId, $start, $end);
    }

    protected function buildTimelineSeries($baseQuery, string $column, Carbon $start, ?Carbon $end, string $grouping, string $seriesName, Carbon $now): array
    {
        return $this->chartBuilder->buildTimelineSeries($baseQuery, $column, $start, $end, $grouping, $seriesName, $now, $this->periodResolver, $this->timezone);
    }

    protected function buildHourlyTimelineSeries($baseQuery, string $column, Carbon $start, string $seriesName): array
    {
        return $this->chartBuilder->buildHourlyTimelineSeries($baseQuery, $column, $start, $seriesName);
    }

    protected function buildStateTimelineSeries(
        $baseQuery,
        string $dateColumn,
        ?string $timeColumn,
        string $stateColumn,
        array $states,
        callable $labelResolver,
        Carbon $start,
        ?Carbon $end,
        string $grouping,
        ?int $doctorId = null,
        ?Carbon $now = null
    ): array {
        return $this->chartBuilder->buildStateTimelineSeries(
            $baseQuery,
            $dateColumn,
            $timeColumn,
            $stateColumn,
            $states,
            $labelResolver,
            $start,
            $end,
            $grouping,
            $doctorId,
            $now,
            $this->periodResolver,
            $this->timezone
        );
    }

    protected function aggregateTimeline(array $dailyCounts, Carbon $start, Carbon $end, string $grouping): array
    {
        return $this->chartBuilder->aggregateTimeline($dailyCounts, $start, $end, $grouping);
    }

    protected function timelineBucketKey(Carbon $date, string $grouping): string
    {
        return $this->chartBuilder->timelineBucketKey($date, $grouping);
    }

    protected function timelineLabel(Carbon $date, string $grouping, bool $crossYear = false): string
    {
        return $this->chartBuilder->timelineLabel($date, $grouping, $crossYear);
    }

    protected function buildRoleSeries(array $usersByRole, ?array $range = null): array
    {
        return $this->adminAnalytics->buildRoleSeries($usersByRole, $range);
    }

    protected function buildSpecialtySeries(?Carbon $start, ?Carbon $end): array
    {
        return $this->adminAnalytics->buildSpecialtySeries($start, $end);
    }

    protected function buildDoctorSeries(?Carbon $start, ?Carbon $end, ?array $range = null): array
    {
        return $this->adminAnalytics->buildDoctorSeries($start, $end, $range);
    }

    protected function buildDocumentSeries(?Carbon $start, ?Carbon $end): array
    {
        return $this->adminAnalytics->buildDocumentSeries($start, $end);
    }

    protected function buildPatientTimelineSeries(Carbon $start, ?Carbon $end, string $grouping, ?int $doctorId = null, ?Carbon $now = null): array
    {
        return $this->doctorAnalytics->buildPatientTimelineSeries($start, $end, $grouping, $doctorId, $now, $this->timezone);
    }

    protected function buildNewVsRecurrentSeries(int $doctorId, ?Carbon $start, ?Carbon $end): array
    {
        return $this->doctorAnalytics->buildNewVsRecurrentSeries($doctorId, $start, $end);
    }

    protected function buildFollowUpSeries(int $doctorId, ?Carbon $start, ?Carbon $end): array
    {
        return $this->doctorAnalytics->buildFollowUpSeries($doctorId, $start, $end, $this->timezone);
    }

    protected function buildDoctorDocumentSeries(int $doctorId, Carbon $start, Carbon $end): array
    {
        return $this->doctorAnalytics->buildDoctorDocumentSeries($doctorId, $start, $end);
    }

    protected function buildDoctorUpcomingAppointments(int $doctorId, int $limit = 5): array
    {
        return $this->doctorAnalytics->buildDoctorUpcomingAppointments($doctorId, $limit);
    }

    protected function buildDoctorUpcomingControls(int $doctorId, int $limit = 5): array
    {
        return $this->doctorAnalytics->buildDoctorUpcomingControls($doctorId, $limit);
    }

    protected function countPatientsAttendedInRange(int $doctorId, ?Carbon $start, ?Carbon $end): int
    {
        return $this->doctorAnalytics->countPatientsAttendedInRange($doctorId, $start, $end);
    }

    protected function countDraftNotes(int $doctorId): int
    {
        return $this->doctorAnalytics->countDraftNotes($doctorId);
    }

    protected function countFutureControls(int $doctorId): int
    {
        return $this->doctorAnalytics->countFutureControls($doctorId, null, $this->timezone);
    }

    protected function countDoctorLabOrders(int $doctorId, bool $onlyCompleted): int
    {
        return $this->doctorAnalytics->countDoctorLabOrders($doctorId, $onlyCompleted);
    }

    protected function countAppointmentsForDoctor(int $doctorId, array $states): int
    {
        return $this->doctorAnalytics->countAppointmentsForDoctor($doctorId, $states);
    }

    protected function nextAppointmentForDoctor(int $doctorId): ?Cita
    {
        return $this->doctorAnalytics->nextAppointmentForDoctor($doctorId, null, $this->timezone);
    }

    protected function formatAppointmentSummary(Cita $cita): string
    {
        return $this->doctorAnalytics->formatAppointmentSummary($cita);
    }

    protected function countLabOrdersCreatedToday(Carbon $start, Carbon $end): int
    {
        return $this->laboratoryAnalytics->countLabOrdersCreatedToday($start, $end);
    }

    protected function buildLabStateSeries(?Carbon $start, ?Carbon $end): array
    {
        return $this->laboratoryAnalytics->buildLabStateSeries($start, $end);
    }

    protected function buildLabTimelineSeries(Carbon $start, ?Carbon $end, string $grouping, Carbon $now): array
    {
        return $this->laboratoryAnalytics->buildLabTimelineSeries($start, $end, $grouping, $now, $this->timezone);
    }

    protected function buildLabExamSeries(?Carbon $start, ?Carbon $end): array
    {
        return $this->laboratoryAnalytics->buildLabExamSeries($start, $end);
    }

    protected function buildLabDoctorSeries(?Carbon $start, ?Carbon $end): array
    {
        return $this->laboratoryAnalytics->buildLabDoctorSeries($start, $end);
    }

    protected function buildRecentAppointments(?Carbon $start, ?Carbon $end): array
    {
        return $this->adminAnalytics->buildRecentAppointments($start, $end);
    }

    protected function buildLaboratoryRecentOrders(int $labUserId, int $limit = 6): array
    {
        return $this->laboratoryAnalytics->buildLaboratoryRecentOrders($labUserId, $limit);
    }

    protected function countDoctorDocuments(int $doctorId, ?Carbon $start, ?Carbon $end): array
    {
        return $this->doctorAnalytics->countDoctorDocuments($doctorId, $start, $end);
    }

    protected function tableExists(string $table): bool
    {
        return $this->chartBuilder->tableExists($table);
    }

    protected function mergeDailyCounts(array $current, array $incoming): array
    {
        return $this->chartBuilder->mergeDailyCounts($current, $incoming);
    }

    protected function mergeNamedCounts(array $current, array $incoming): array
    {
        return $this->chartBuilder->mergeNamedCounts($current, $incoming);
    }

    protected function metric(int $value, ?string $subtitle = null): array
    {
        return $this->chartBuilder->metric($value, $subtitle);
    }

    protected function labelForRole(string $role): string
    {
        return $this->chartBuilder->labelForRole($role);
    }

    protected function toneForState(string $state): string
    {
        return $this->chartBuilder->toneForState($state);
    }

    protected function toneForLabState(string $state): string
    {
        return $this->chartBuilder->toneForLabState($state);
    }

    protected function toneForLabOrderState(string $state): string
    {
        return $this->chartBuilder->toneForLabOrderState($state);
    }

    protected function buildDoctorAppointmentsStacked(?Carbon $start, ?Carbon $end, ?array $range = null): array
    {
        return $this->adminAnalytics->buildDoctorAppointmentsStacked($start, $end, $range);
    }

    protected function buildPatientsNewVsAttended(Carbon $start, ?Carbon $end, string $grouping, ?Carbon $now = null): array
    {
        return $this->adminAnalytics->buildPatientsNewVsAttended($start, $end, $grouping, $now, $this->timezone);
    }

    protected function stateColors(int $count): array
    {
        return $this->chartBuilder->stateColors($count);
    }
}
