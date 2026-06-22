<?php

declare(strict_types=1);

final class AdlaireEventLog
{
    private const DOMAINS = ['realtime_database', 'authentication', 'authorization'];
    private const TYPES = [
        'create',
        'update',
        'delete',
        'restore',
        'user_create',
        'user_update',
        'credential_register',
        'credential_revoke',
        'credential_rotate',
        'session_issue',
        'session_validate',
        'session_revoke',
        'login_success',
        'login_failure',
        'password_policy_check',
        'role_create',
        'permission_create',
        'policy_assign',
        'policy_revoke',
        'policy_evaluate',
        'access_allow',
        'access_deny',
        'policy_conflict_detect',
        'least_privilege_review',
        'auth_change_impact_report',
        'policy_simulation',
        'session_revocation_impact',
        'credential_revocation_impact',
        'permission_coverage_report',
        'unused_permission_report',
        'dormant_user_report',
        'stale_session_report',
        'failed_login_trend',
        'access_pattern_baseline',
        'access_pattern_drift_report',
        'role_saturation_report',
        'policy_expiry_plan',
        'emergency_access_review',
        'auth_evidence_export',
        'auth_evidence_import_validation',
        'auth_state_compare',
        'authorization_regression_guard',
        'auth_operations_ledger',
        'auth_control_summary',
    ];

    public static function role(): array
    {
        return [
            'file' => 'Core/EventLog.php',
            'role' => 'common_foundation',
            'single_file' => true,
            'folder' => 'prohibited',
            'entrypoint' => false,
            'shared_by' => ['realtime_database', 'authentication', 'authorization'],
            'trust_foundation' => true,
            'auto_mutation' => false,
        ];
    }

    public static function typeRegistry(): array
    {
        return [
            'domains' => self::DOMAINS,
            'types' => self::TYPES,
            'unknown_type_allowed' => false,
        ];
    }

    public static function recordEvent(
        array $events,
        string $collection,
        string $channel,
        string $recordId,
        string $type,
        int $version,
        array $payload,
        ?array $before,
        array $metadata = []
    ): array {
        return self::recordDomainEvent($events, 'realtime_database', $collection, $channel, $recordId, $type, $version, $payload, $before, $metadata);
    }

    public static function recordDomainEvent(
        array $events,
        string $domain,
        string $collection,
        string $channel,
        string $recordId,
        string $type,
        int $version,
        array $payload,
        ?array $before = null,
        array $metadata = []
    ): array {
        $sequence = count($events) + 1;
        $afterHash = hash('sha256', self::encodeJson(self::stableData($payload)));
        $previous = $events === [] ? null : $events[array_key_last($events)];
        $previousHash = is_array($previous) && isset($previous['event_hash'])
            ? (string)$previous['event_hash']
            : ($previous === null ? 'root' : self::hashEvent($previous, 'root'));

        $event = [
            'id' => 'evt_' . str_pad((string)$sequence, 6, '0', STR_PAD_LEFT),
            'sequence' => $sequence,
            'source' => $domain,
            'domain' => $domain,
            'collection' => $collection,
            'channel' => $channel,
            'record_id' => $recordId,
            'type' => $type,
            'version' => $version,
            'envelope_version' => 1,
            'metadata' => self::metadata($metadata),
            'payload_hash' => $afterHash,
            'before_hash' => $before === null ? null : hash('sha256', self::encodeJson(self::stableData($before))),
            'after_hash' => $type === 'delete' ? null : $afterHash,
            'changed_fields' => self::changedFields($before, $payload),
            'previous_event_id' => is_array($previous) ? ($previous['id'] ?? null) : null,
            'previous_hash' => $previousHash,
            'snapshot_fingerprint' => null,
            'payload' => self::stableData($payload),
        ];
        $event['event_hash'] = self::hashEvent($event, $previousHash);

        return $event;
    }

    public static function events(array $events, ?string $after = null, ?string $collection = null): array
    {
        $filtered = self::filterEvents($events, $collection);
        if ($after === null) {
            return $filtered;
        }

        $found = false;
        $cursorEvents = [];
        foreach ($filtered as $event) {
            if ($found) {
                $cursorEvents[] = $event;
                continue;
            }
            if (($event['id'] ?? null) === $after) {
                $found = true;
            }
        }

        return $cursorEvents;
    }

    public static function replay(string $collection, array $events, array $collectionDefinition): array
    {
        $records = [];
        foreach ($events as $event) {
            if (($event['collection'] ?? null) !== $collection) {
                continue;
            }
            $recordId = (string)($event['record_id'] ?? '');
            if (($event['type'] ?? null) === 'delete') {
                unset($records[$recordId]);
                continue;
            }
            $payload = $event['payload'] ?? [];
            if (is_array($payload)) {
                $records[$recordId] = [
                    'id' => $recordId,
                    'collection' => $collection,
                    'channel' => (string)$collectionDefinition['channel'],
                    'data' => self::stableData($payload),
                    'meta' => [
                        'created_sequence' => (int)($event['sequence'] ?? 0),
                        'updated_sequence' => (int)($event['sequence'] ?? 0),
                        'deleted_sequence' => null,
                        'revision' => (int)($event['version'] ?? 1),
                    ],
                    'version' => (int)($event['version'] ?? 1),
                ];
            }
        }

        $payload = [
            'collection' => $collection,
            'records' => array_values($records),
            'version' => count($events),
            'cursor' => self::lastEventId($events),
        ];

        return $payload + [
            'fingerprint' => hash('sha256', self::encodeJson($payload)),
        ];
    }

    public static function cursor(array $events): array
    {
        $integrity = self::eventChainIntegrity($events);

        return [
            'after' => null,
            'latest' => self::lastEventId($events),
            'sequence' => count($events),
            'chain_hash' => $integrity['tip'],
        ];
    }

    public static function cursorContract(array $events, ?string $eventId = null): array
    {
        $target = null;
        foreach ($events as $event) {
            if ($eventId === null || ($event['id'] ?? null) === $eventId) {
                $target = $event;
            }
        }
        $chain = self::eventChainIntegrity($events);

        return [
            'event_id' => is_array($target) ? ($target['id'] ?? null) : null,
            'sequence' => is_array($target) ? (int)($target['sequence'] ?? 0) : 0,
            'chain_hash' => $eventId === null ? $chain['tip'] : (is_array($target) ? ($target['event_hash'] ?? null) : null),
            'valid' => $target !== null && $chain['valid'] === true,
        ];
    }

    public static function eventCheckpoint(array $events, ?string $cursor = null): array
    {
        $checkpointEvents = [];
        foreach ($events as $event) {
            $checkpointEvents[] = $event;
            if ($cursor !== null && ($event['id'] ?? null) === $cursor) {
                break;
            }
        }

        $collections = [];
        foreach ($checkpointEvents as $event) {
            $collection = (string)($event['collection'] ?? '');
            $collections[$collection] = ($collections[$collection] ?? 0) + 1;
        }
        ksort($collections);

        return [
            'cursor' => $cursor ?? self::lastEventId($checkpointEvents),
            'event_count' => count($checkpointEvents),
            'collections' => $collections,
            'fingerprint' => hash('sha256', self::encodeJson(['cursor' => $cursor, 'events' => $checkpointEvents])),
        ];
    }

    public static function consistencyCheck(array $events): array
    {
        $errors = [];
        $previous = 0;
        $seen = [];
        foreach ($events as $event) {
            $id = (string)($event['id'] ?? '');
            $sequence = (int)($event['sequence'] ?? 0);
            $domain = (string)($event['domain'] ?? 'realtime_database');
            $type = (string)($event['type'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                $errors[] = ['type' => 'duplicate_or_missing_event_id', 'event' => $id];
            }
            if (!in_array($domain, self::DOMAINS, true)) {
                $errors[] = ['type' => 'unknown_domain', 'event' => $id, 'domain' => $domain];
            }
            if (!in_array($type, self::TYPES, true)) {
                $errors[] = ['type' => 'unknown_type', 'event' => $id, 'event_type' => $type];
            }
            if (!isset($event['payload']) || !is_array($event['payload'])) {
                $errors[] = ['type' => 'invalid_payload', 'event' => $id];
            }
            $seen[$id] = true;
            if ($sequence !== $previous + 1) {
                $errors[] = ['type' => 'event_sequence_gap', 'event' => $id, 'expected' => $previous + 1, 'actual' => $sequence];
            }
            $previous = $sequence;
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'event_count' => count($events),
            'latest_cursor' => self::lastEventId($events),
        ];
    }

    public static function gapReport(array $events): array
    {
        $report = self::consistencyCheck($events);

        return [
            'valid' => $report['valid'],
            'gaps' => array_values(array_filter(
                $report['errors'],
                static fn(array $error): bool => ($error['type'] ?? null) === 'event_sequence_gap'
            )),
            'errors' => $report['errors'],
            'latest_cursor' => $report['latest_cursor'],
        ];
    }

    public static function eventChainIntegrity(array $events): array
    {
        $errors = [];
        $previousSequence = 0;
        $previousChain = 'root';
        $chain = [];
        foreach ($events as $event) {
            $sequence = (int)($event['sequence'] ?? 0);
            if ($sequence !== $previousSequence + 1) {
                $errors[] = ['type' => 'event_sequence_gap', 'event' => $event['id'] ?? null];
            }
            $current = self::hashEvent($event, $previousChain);
            if (isset($event['previous_hash']) && $event['previous_hash'] !== $previousChain) {
                $errors[] = ['type' => 'chain_previous_hash_mismatch', 'event' => $event['id'] ?? null];
            }
            if (isset($event['event_hash']) && $event['event_hash'] !== $current) {
                $errors[] = ['type' => 'chain_event_hash_mismatch', 'event' => $event['id'] ?? null];
            }
            $chain[] = [
                'event_id' => $event['id'] ?? null,
                'sequence' => $sequence,
                'chain_hash' => $current,
            ];
            $previousSequence = $sequence;
            $previousChain = $current;
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'event_count' => count($events),
            'root' => 'root',
            'tip' => $previousChain,
            'chain' => $chain,
        ];
    }

    public static function streamIntegritySummary(array $events): array
    {
        $consistency = self::consistencyCheck($events);
        $chain = self::eventChainIntegrity($events);

        return [
            'valid' => $consistency['valid'] === true && $chain['valid'] === true,
            'event_count' => count($events),
            'latest_cursor' => self::lastEventId($events),
            'sequence_valid' => $consistency['valid'],
            'chain_valid' => $chain['valid'],
            'chain_tip' => $chain['tip'],
            'errors' => array_merge($consistency['errors'], $chain['errors']),
        ];
    }

    public static function replayProof(string $collection, array $snapshot, array $rebuilt): array
    {
        $snapshotHash = hash('sha256', self::encodeJson(self::readModelPayload($snapshot)));
        $rebuiltHash = hash('sha256', self::encodeJson(self::readModelPayload($rebuilt)));

        return [
            'collection' => $collection,
            'proved' => $snapshotHash === $rebuiltHash,
            'snapshot_fingerprint' => $snapshotHash,
            'rebuilt_fingerprint' => $rebuiltHash,
            'event_count' => count($rebuilt['records'] ?? []),
            'will_repair' => false,
        ];
    }

    public static function replayScope(
        array $events,
        ?string $domain = null,
        ?string $collection = null,
        ?string $recordId = null,
        ?int $fromSequence = null,
        ?int $toSequence = null
    ): array {
        $scoped = array_values(array_filter($events, static function (array $event) use ($domain, $collection, $recordId, $fromSequence, $toSequence): bool {
            $sequence = (int)($event['sequence'] ?? 0);
            return ($domain === null || ($event['domain'] ?? 'realtime_database') === $domain)
                && ($collection === null || ($event['collection'] ?? null) === $collection)
                && ($recordId === null || ($event['record_id'] ?? null) === $recordId)
                && ($fromSequence === null || $sequence >= $fromSequence)
                && ($toSequence === null || $sequence <= $toSequence);
        }));

        return [
            'events' => $scoped,
            'count' => count($scoped),
            'cursor' => self::lastEventId($scoped),
            'fingerprint' => hash('sha256', self::encodeJson(['events' => $scoped])),
        ];
    }

    public static function evidence(array $events): array
    {
        $chain = self::eventChainIntegrity($events);

        return [
            'fingerprint' => hash('sha256', self::encodeJson(['events' => $events])),
            'event_count' => count($events),
            'latest_sequence' => count($events),
            'latest_event_id' => self::lastEventId($events),
            'chain_tip' => $chain['tip'],
            'valid' => $chain['valid'] === true && self::consistencyCheck($events)['valid'] === true,
        ];
    }

    public static function snapshotLink(array $events, string $eventId, string $snapshotFingerprint): array
    {
        return [
            'event_id' => $eventId,
            'snapshot_fingerprint' => $snapshotFingerprint,
            'linked' => $eventId !== '' && $snapshotFingerprint !== '',
            'event_exists' => self::findEvent($events, $eventId) !== null,
        ];
    }

    public static function replayVerification(string $collection, array $snapshot, array $rebuilt): array
    {
        $proof = self::replayProof($collection, $snapshot, $rebuilt);

        return [
            'collection' => $collection,
            'verified' => $proof['proved'],
            'snapshot_fingerprint' => $proof['snapshot_fingerprint'],
            'rebuilt_fingerprint' => $proof['rebuilt_fingerprint'],
            'difference' => $proof['proved'] ? 'none' : 'snapshot_replay_mismatch',
            'rebuild_possible' => true,
        ];
    }

    public static function importValidation(array $events): array
    {
        $consistency = self::consistencyCheck($events);
        $chain = self::eventChainIntegrity($events);
        $errors = array_merge($consistency['errors'], $chain['errors']);

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'event_count' => count($events),
            'will_import' => false,
        ];
    }

    public static function exportPacket(array $events): array
    {
        $domains = [];
        foreach ($events as $event) {
            $domain = (string)($event['domain'] ?? 'realtime_database');
            $domains[$domain] = ($domains[$domain] ?? 0) + 1;
        }
        ksort($domains);

        return [
            'kind' => 'event_log_export_packet',
            'events' => array_values($events),
            'event_count' => count($events),
            'domain_count' => $domains,
            'cursor' => self::cursorContract($events),
            'fingerprint' => hash('sha256', self::encodeJson(['events' => $events, 'domains' => $domains])),
        ];
    }

    public static function retentionView(array $events): array
    {
        return [
            'event_count' => count($events),
            'latest_event_id' => self::lastEventId($events),
            'delete_candidates' => [],
            'automatic_delete' => false,
            'automatic_compaction' => false,
        ];
    }

    public static function riskReport(array $events): array
    {
        $errors = array_merge(self::consistencyCheck($events)['errors'], self::eventChainIntegrity($events)['errors']);
        $risk = [];
        foreach ($errors as $error) {
            $type = (string)($error['type'] ?? 'unknown');
            $risk[$type] = ($risk[$type] ?? 0) + 1;
        }
        ksort($risk);

        return [
            'risk_count' => array_sum($risk),
            'risks' => $risk,
            'status' => $risk === [] ? 'clear' : 'review_required',
            'automatic_repair' => false,
        ];
    }

    public static function operationJournal(string $operation, array $events, array $result = []): array
    {
        return [
            'operation' => $operation,
            'event_count' => count($events),
            'latest_event_id' => self::lastEventId($events),
            'result' => self::stableData($result),
            'will_mutate_event_log' => false,
        ];
    }

    public static function healthSummary(array $events): array
    {
        $evidence = self::evidence($events);
        $risk = self::riskReport($events);

        return [
            'event_count' => count($events),
            'latest_cursor' => self::lastEventId($events),
            'chain_valid' => self::eventChainIntegrity($events)['valid'],
            'risk_status' => $risk['status'],
            'ready' => $evidence['valid'] === true && $risk['status'] === 'clear',
        ];
    }

    public static function recoveryEvidence(array $events, array $snapshot = [], array $rebuilt = []): array
    {
        return [
            'evidence' => self::evidence($events),
            'risk' => self::riskReport($events),
            'chain' => self::eventChainIntegrity($events),
            'replay_verification' => $snapshot === [] || $rebuilt === [] ? ['verified' => false, 'difference' => 'not_provided'] : self::replayVerification((string)($snapshot['collection'] ?? ''), $snapshot, $rebuilt),
            'restore_readiness' => self::restoreReadiness($events),
        ];
    }

    public static function operationalGuard(array $events): array
    {
        $risk = self::riskReport($events);

        return [
            'status' => $risk['status'] === 'clear' ? 'normal' : 'blocked',
            'write_allowed' => $risk['status'] === 'clear',
            'risk' => $risk,
            'automatic_repair' => false,
        ];
    }

    public static function trustScore(array $events): array
    {
        $risk = self::riskReport($events);
        $score = max(0, 100 - ($risk['risk_count'] * 20));

        return [
            'score' => $score,
            'level' => $score >= 90 ? 'high' : ($score >= 60 ? 'medium' : 'low'),
            'risk_count' => $risk['risk_count'],
        ];
    }

    public static function restoreReadiness(array $events): array
    {
        $health = self::healthSummary($events);

        return [
            'status' => $health['ready'] ? 'ready' : 'manual_review_required',
            'event_count' => count($events),
            'latest_cursor' => self::lastEventId($events),
            'will_restore' => false,
        ];
    }

    public static function auditPacket(array $events): array
    {
        $export = self::exportPacket($events);

        return [
            'evidence' => self::evidence($events),
            'risk' => self::riskReport($events),
            'cursor' => self::cursorContract($events),
            'chain_tip' => self::eventChainIntegrity($events)['tip'],
            'export_fingerprint' => $export['fingerprint'],
        ];
    }

    public static function incidentPacket(array $events): array
    {
        return [
            'health' => self::healthSummary($events),
            'recovery_evidence' => self::recoveryEvidence($events),
            'degradation' => self::degradationReport($events),
            'handoff' => self::handoffSummary($events),
        ];
    }

    public static function degradationReport(array $events): array
    {
        $risk = self::riskReport($events);
        $status = $risk['risk_count'] === 0 ? 'normal' : ($risk['risk_count'] <= 2 ? 'watch' : 'degraded');

        return [
            'status' => $status,
            'risk_count' => $risk['risk_count'],
            'blocked' => $status === 'degraded',
        ];
    }

    public static function writeSafetyGate(array $events, string $type, string $domain = 'realtime_database'): array
    {
        $registry = self::typeRegistry();
        $safe = self::riskReport($events)['status'] === 'clear'
            && in_array($type, $registry['types'], true)
            && in_array($domain, $registry['domains'], true);

        return [
            'allowed' => $safe,
            'expected_sequence' => count($events) + 1,
            'chain_tip' => self::eventChainIntegrity($events)['tip'],
            'type_known' => in_array($type, $registry['types'], true),
            'domain_known' => in_array($domain, $registry['domains'], true),
        ];
    }

    public static function replayWindow(array $events, int $fromSequence, int $toSequence): array
    {
        $scope = self::replayScope($events, null, null, null, $fromSequence, $toSequence);

        return [
            'from_sequence' => $fromSequence,
            'to_sequence' => $toSequence,
            'event_count' => $scope['count'],
            'safe' => self::importValidation($scope['events'])['valid'],
        ];
    }

    public static function cursorDriftReport(array $events, ?string $cursor): array
    {
        $contract = self::cursorContract($events, $cursor);

        return [
            'cursor' => $cursor,
            'valid' => $contract['valid'],
            'drift' => $cursor !== null && $contract['event_id'] !== $cursor,
            'current' => self::cursorContract($events),
        ];
    }

    public static function exportIntegrity(array $packet): array
    {
        $events = is_array($packet['events'] ?? null) ? $packet['events'] : [];
        $expected = hash('sha256', self::encodeJson(['events' => $events, 'domains' => $packet['domain_count'] ?? []]));

        return [
            'valid' => ($packet['fingerprint'] ?? null) === $expected,
            'event_count' => count($events),
            'chain_tip' => self::eventChainIntegrity($events)['tip'],
        ];
    }

    public static function restoreImpact(array $candidateEvents, array $currentEvents): array
    {
        $collections = [];
        $records = [];
        foreach ($candidateEvents as $event) {
            $collections[(string)($event['collection'] ?? '')] = true;
            $records[(string)($event['record_id'] ?? '')] = true;
        }

        return [
            'events_added' => max(0, count($candidateEvents) - count($currentEvents)),
            'records_affected' => count(array_filter(array_keys($records))),
            'collections_affected' => count(array_filter(array_keys($collections))),
            'latest_cursor' => self::lastEventId($candidateEvents),
        ];
    }

    public static function retentionDecisionView(array $events): array
    {
        return self::retentionView($events) + [
            'decision' => 'manual_review',
            'evacuate_candidates' => [],
            'keep_all' => true,
        ];
    }

    public static function operationalSlo(array $events): array
    {
        return [
            'chain_validity' => self::eventChainIntegrity($events)['valid'],
            'sequence_continuity' => self::consistencyCheck($events)['valid'],
            'risk_clear' => self::riskReport($events)['status'] === 'clear',
            'met' => self::healthSummary($events)['ready'],
        ];
    }

    public static function handoffSummary(array $events): array
    {
        $risk = self::riskReport($events);

        return [
            'current_status' => self::degradationReport($events)['status'],
            'latest_cursor' => self::lastEventId($events),
            'risk' => $risk['status'],
            'recommended_action' => $risk['status'] === 'clear' ? 'continue_observation' : 'manual_review',
        ];
    }

    public static function preflightReport(array $events, string $type, string $domain, array $payload = []): array
    {
        $gate = self::writeSafetyGate($events, $type, $domain);

        return $gate + [
            'payload_hash' => hash('sha256', self::encodeJson(self::stableData($payload))),
            'metadata_required' => true,
        ];
    }

    public static function chainSnapshot(array $events): array
    {
        return [
            'chain_tip' => self::eventChainIntegrity($events)['tip'],
            'latest_cursor' => self::lastEventId($events),
            'event_count' => count($events),
            'domain_count' => self::exportPacket($events)['domain_count'],
        ];
    }

    public static function continuityProof(array $events): array
    {
        $consistency = self::consistencyCheck($events);
        $chain = self::eventChainIntegrity($events);

        return [
            'proved' => $consistency['valid'] === true && $chain['valid'] === true,
            'sequence_continuity' => $consistency['valid'],
            'hash_continuity' => $chain['valid'],
            'errors' => array_merge($consistency['errors'], $chain['errors']),
        ];
    }

    public static function payloadIntegrityReport(array $events): array
    {
        $errors = [];
        foreach ($events as $event) {
            $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
            $hash = hash('sha256', self::encodeJson(self::stableData($payload)));
            if (($event['payload_hash'] ?? null) !== $hash) {
                $errors[] = ['type' => 'payload_hash_mismatch', 'event' => $event['id'] ?? null];
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'event_count' => count($events),
        ];
    }

    public static function domainIsolationReport(array $events): array
    {
        $domains = [];
        foreach (self::DOMAINS as $domain) {
            $scope = self::replayScope($events, $domain);
            $domains[$domain] = [
                'event_count' => $scope['count'],
                'latest_cursor' => self::lastEventId($scope['events']),
                'risk' => self::riskReport($scope['events'])['status'],
            ];
        }

        return $domains;
    }

    public static function recoveryRoute(array $events): array
    {
        $ready = self::restoreReadiness($events);

        return [
            'route' => $ready['status'] === 'ready' ? 'replay' : 'manual_review',
            'restore_readiness' => $ready,
            'automatic_restore' => false,
        ];
    }

    public static function manualReviewQueue(array $events): array
    {
        $errors = self::continuityProof($events)['errors'];

        return [
            'count' => count($errors),
            'items' => $errors,
            'automatic_repair' => false,
        ];
    }

    public static function operationalTimeline(array $events): array
    {
        $items = [];
        foreach ($events as $event) {
            $items[] = [
                'sequence' => $event['sequence'] ?? null,
                'type' => $event['type'] ?? null,
                'collection' => $event['collection'] ?? null,
                'record_id' => $event['record_id'] ?? null,
                'chain_hash' => $event['event_hash'] ?? null,
            ];
        }

        return [
            'items' => $items,
            'count' => count($items),
        ];
    }

    public static function evidenceSeal(array $events): array
    {
        $evidence = self::evidence($events);

        return [
            'evidence' => $evidence,
            'seal' => hash('sha256', self::encodeJson($evidence)),
            'verified' => $evidence['valid'],
        ];
    }

    public static function trustLedger(array $events): array
    {
        return [
            'trust_score' => self::trustScore($events),
            'risk_report' => self::riskReport($events),
            'continuity_proof' => self::continuityProof($events),
            'evidence_seal' => self::evidenceSeal($events),
        ];
    }

    public static function causalityChain(array $events): array
    {
        $items = [];
        foreach ($events as $event) {
            $items[] = [
                'event_id' => $event['id'] ?? null,
                'sequence' => $event['sequence'] ?? null,
                'collection' => $event['collection'] ?? null,
                'record_id' => $event['record_id'] ?? null,
                'type' => $event['type'] ?? null,
                'cause' => ($event['type'] ?? 'unknown') . ':' . ($event['collection'] ?? 'unknown'),
            ];
        }

        return [
            'items' => $items,
            'count' => count($items),
            'latest_cursor' => self::lastEventId($events),
        ];
    }

    public static function payloadEventGapReport(array $events): array
    {
        return self::consistencyCheck($events);
    }

    public static function lastEventId(array $events): ?string
    {
        if ($events === []) {
            return null;
        }

        return (string)$events[array_key_last($events)]['id'];
    }

    private static function filterEvents(array $events, ?string $collection): array
    {
        if ($collection === null) {
            return array_values($events);
        }

        return array_values(array_filter(
            $events,
            static fn(array $event): bool => ($event['collection'] ?? null) === $collection
        ));
    }

    private static function findEvent(array $events, string $eventId): ?array
    {
        foreach ($events as $event) {
            if (($event['id'] ?? null) === $eventId) {
                return $event;
            }
        }

        return null;
    }

    private static function metadata(array $metadata): array
    {
        return [
            'actor' => (string)($metadata['actor'] ?? 'system'),
            'reason' => (string)($metadata['reason'] ?? 'unspecified'),
            'operation' => (string)($metadata['operation'] ?? 'database_write'),
            'request_id' => (string)($metadata['request_id'] ?? ''),
            'trace_id' => (string)($metadata['trace_id'] ?? ''),
        ];
    }

    private static function hashEvent(array $event, string $previousHash): string
    {
        $payload = $event;
        unset($payload['event_hash']);
        $payload['previous_hash'] = $previousHash;

        return hash('sha256', self::encodeJson(self::stableData($payload)));
    }

    private static function changedFields(?array $before, array $after): array
    {
        $fields = array_unique(array_merge(array_keys($before ?? []), array_keys($after)));
        $changed = [];
        foreach ($fields as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changed[] = $field;
            }
        }
        sort($changed);

        return $changed;
    }

    private static function readModelPayload(array $snapshot): array
    {
        $records = [];
        foreach (($snapshot['records'] ?? []) as $record) {
            if (!is_array($record) || !isset($record['id'])) {
                continue;
            }
            $records[(string)$record['id']] = [
                'id' => (string)$record['id'],
                'data' => self::stableData($record['data'] ?? []),
                'version' => (int)($record['version'] ?? 1),
            ];
        }
        ksort($records);

        return [
            'collection' => (string)($snapshot['collection'] ?? ''),
            'records' => array_values($records),
        ];
    }

    private static function stableData(array $data): array
    {
        ksort($data);
        return $data;
    }

    private static function encodeJson(array $payload): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            throw new RuntimeException('Failed to encode Event Log JSON payload.');
        }

        return $encoded;
    }
}
