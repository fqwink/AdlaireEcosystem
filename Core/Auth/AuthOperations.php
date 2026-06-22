<?php

declare(strict_types=1);

trait AdlaireAuthOperations
{
    public static function passwordPolicy(): array
    {
        return [
            'min_length' => 12,
            'plain_password_storage' => false,
            'auto_rotation' => false,
        ];
    }

    public static function createUser(string $status = 'active'): array
    {
        self::assertStatus($status);
        $id = self::nextId('usr', ++self::$userSequence);
        $user = [
            'id' => $id,
            'status' => $status,
            'created_sequence' => count(self::$events) + 1,
            'updated_sequence' => count(self::$events) + 1,
        ];
        self::$users[$id] = $user;
        self::recordAuthEvent('authentication', 'user_create', $id, $user);

        return $user;
    }

    public static function updateUser(string $userId, string $status): array
    {
        self::assertUser($userId);
        self::assertStatus($status);
        self::$users[$userId]['status'] = $status;
        self::$users[$userId]['updated_sequence'] = count(self::$events) + 1;
        self::recordAuthEvent('authentication', 'user_update', $userId, self::$users[$userId]);

        return self::$users[$userId];
    }

    public static function registerCredential(string $userId, string $secret, string $type = 'password'): array
    {
        self::assertUser($userId);
        if (strlen($secret) < self::passwordPolicy()['min_length']) {
            self::recordAuthEvent('authentication', 'password_policy_check', $userId, ['valid' => false, 'reason' => 'too_short']);
            throw new InvalidArgumentException('Credential secret does not satisfy password policy.');
        }
        $id = self::nextId('cred', ++self::$credentialSequence);
        $credential = [
            'id' => $id,
            'user_id' => $userId,
            'type' => $type,
            'secret_hash' => self::hashSecret($secret),
            'status' => 'active',
        ];
        self::$credentials[$id] = $credential;
        self::recordAuthEvent('authentication', 'credential_register', $id, self::redactCredential($credential));

        return self::redactCredential($credential);
    }

    public static function rotateCredential(string $credentialId, string $secret): array
    {
        self::assertCredential($credentialId);
        if (strlen($secret) < self::passwordPolicy()['min_length']) {
            throw new InvalidArgumentException('Credential secret does not satisfy password policy.');
        }
        self::$credentials[$credentialId]['secret_hash'] = self::hashSecret($secret);
        self::recordAuthEvent('authentication', 'credential_rotate', $credentialId, self::redactCredential(self::$credentials[$credentialId]));

        return self::redactCredential(self::$credentials[$credentialId]);
    }

    public static function revokeCredential(string $credentialId): array
    {
        self::assertCredential($credentialId);
        self::$credentials[$credentialId]['status'] = 'revoked';
        self::recordAuthEvent('authentication', 'credential_revoke', $credentialId, self::redactCredential(self::$credentials[$credentialId]));

        return self::redactCredential(self::$credentials[$credentialId]);
    }

    public static function login(string $credentialId, string $secret): array
    {
        self::assertCredential($credentialId);
        $credential = self::$credentials[$credentialId];
        $user = self::$users[$credential['user_id']] ?? null;
        $allowed = is_array($user)
            && $user['status'] === 'active'
            && $credential['status'] === 'active'
            && hash_equals((string)$credential['secret_hash'], self::hashSecret($secret));
        if (!$allowed) {
            self::recordAuthEvent('authentication', 'login_failure', $credentialId, ['credential_id' => $credentialId, 'reason' => 'credential_invalid']);
            return ['authenticated' => false, 'reason' => 'credential_invalid'];
        }

        $session = self::issueSession((string)$credential['user_id']);
        self::recordAuthEvent('authentication', 'login_success', $credentialId, ['credential_id' => $credentialId, 'session_id' => $session['id']]);

        return ['authenticated' => true, 'session' => $session];
    }

    public static function issueSession(string $userId): array
    {
        self::assertUser($userId);
        if (self::$users[$userId]['status'] !== 'active') {
            throw new RuntimeException('Inactive user cannot receive session.');
        }
        $id = self::nextId('sess', ++self::$sessionSequence);
        $session = [
            'id' => $id,
            'user_id' => $userId,
            'status' => 'active',
            'issued_sequence' => count(self::$events) + 1,
            'revoked_sequence' => null,
        ];
        self::$sessions[$id] = $session;
        self::recordAuthEvent('authentication', 'session_issue', $id, $session);

        return $session;
    }

    public static function validateSession(string $sessionId): array
    {
        $session = self::$sessions[$sessionId] ?? null;
        $valid = is_array($session) && $session['status'] === 'active' && (self::$users[$session['user_id']]['status'] ?? null) === 'active';
        self::recordAuthEvent('authentication', 'session_validate', $sessionId, ['valid' => $valid, 'session_id' => $sessionId]);

        return ['valid' => $valid, 'session' => $session];
    }

    public static function revokeSession(string $sessionId): array
    {
        self::assertSession($sessionId);
        self::$sessions[$sessionId]['status'] = 'revoked';
        self::$sessions[$sessionId]['revoked_sequence'] = count(self::$events) + 1;
        self::recordAuthEvent('authentication', 'session_revoke', $sessionId, self::$sessions[$sessionId]);

        return self::$sessions[$sessionId];
    }

    public static function createRole(string $name, string $status = 'active'): array
    {
        self::assertStatus($status);
        $id = self::nextId('role', ++self::$roleSequence);
        $role = ['id' => $id, 'name' => $name, 'status' => $status];
        self::$roles[$id] = $role;
        self::recordAuthEvent('authorization', 'role_create', $id, $role);

        return $role;
    }

    public static function createPermission(string $resource, string $action, string $status = 'active'): array
    {
        self::assertStatus($status);
        $id = self::nextId('perm', ++self::$permissionSequence);
        $permission = ['id' => $id, 'resource' => $resource, 'action' => $action, 'status' => $status];
        self::$permissions[$id] = $permission;
        self::recordAuthEvent('authorization', 'permission_create', $id, $permission);

        return $permission;
    }

    public static function assignPolicy(string $subjectId, string $roleId, string $permissionId, string $effect = 'allow'): array
    {
        self::assertUser($subjectId);
        self::assertRole($roleId);
        self::assertPermission($permissionId);
        if (!in_array($effect, ['allow', 'deny'], true)) {
            throw new InvalidArgumentException('Unsupported policy effect.');
        }
        $id = self::nextId('policy', ++self::$policySequence);
        $policy = [
            'id' => $id,
            'subject_id' => $subjectId,
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'effect' => $effect,
            'status' => 'active',
        ];
        self::$policies[$id] = $policy;
        self::recordAuthEvent('authorization', 'policy_assign', $id, $policy);

        return $policy;
    }

    public static function revokePolicy(string $policyId): array
    {
        self::assertPolicy($policyId);
        self::$policies[$policyId]['status'] = 'revoked';
        self::recordAuthEvent('authorization', 'policy_revoke', $policyId, self::$policies[$policyId]);

        return self::$policies[$policyId];
    }

    public static function accessDecision(string $sessionId, string $resource, string $action): array
    {
        $session = self::validateSession($sessionId);
        if ($session['valid'] !== true || !is_array($session['session'])) {
            return self::decision($sessionId, null, $resource, $action, false, 'revoked_session');
        }
        $userId = (string)$session['session']['user_id'];
        foreach (self::$policies as $policy) {
            if (($policy['subject_id'] ?? null) !== $userId || ($policy['status'] ?? null) !== 'active') {
                continue;
            }
            $role = self::$roles[$policy['role_id']] ?? null;
            $permission = self::$permissions[$policy['permission_id']] ?? null;
            if (($role['status'] ?? null) !== 'active') {
                return self::decision($sessionId, $policy, $resource, $action, false, 'inactive_role');
            }
            if (($permission['status'] ?? null) !== 'active') {
                return self::decision($sessionId, $policy, $resource, $action, false, 'inactive_permission');
            }
            if (($permission['resource'] ?? null) === $resource && ($permission['action'] ?? null) === $action) {
                return self::decision($sessionId, $policy, $resource, $action, ($policy['effect'] ?? null) === 'allow', ($policy['effect'] ?? null) === 'allow' ? 'matched_policy' : 'explicit_deny');
            }
        }

        return self::decision($sessionId, null, $resource, $action, false, 'no_policy');
    }

    public static function permissionMatrix(): array
    {
        return [
            'subjects' => self::$users,
            'roles' => self::$roles,
            'permissions' => self::$permissions,
            'policies' => self::$policies,
        ];
    }

    public static function denyReasonRegistry(): array
    {
        return ['no_policy', 'inactive_user', 'revoked_session', 'inactive_role', 'inactive_permission', 'explicit_deny'];
    }

    public static function authorizationScopeBoundary(): array
    {
        return [
            'resources' => ['collection', 'record', 'auth_resource'],
            'actions' => ['read', 'create', 'update', 'delete', 'restore', 'admin'],
            'undefined_policy' => 'deny',
        ];
    }

    public static function policyConflictReport(): array
    {
        $seen = [];
        $conflicts = [];
        foreach (self::$policies as $policy) {
            if (($policy['status'] ?? null) !== 'active') {
                continue;
            }
            $key = $policy['subject_id'] . ':' . $policy['permission_id'];
            if (isset($seen[$key]) && $seen[$key] !== $policy['effect']) {
                $conflicts[] = $policy;
            }
            $seen[$key] = $policy['effect'];
        }
        return ['conflict' => $conflicts !== [], 'items' => $conflicts, 'count' => count($conflicts)];
    }

    public static function leastPrivilegeReport(string $subjectId): array
    {
        $permissions = array_values(array_filter(self::$policies, static fn(array $policy): bool => ($policy['subject_id'] ?? null) === $subjectId && ($policy['status'] ?? null) === 'active'));
        return [
            'subject_id' => $subjectId,
            'permission_count' => count($permissions),
            'review_required' => count($permissions) > 10,
        ];
    }

    public static function authEvents(?string $domain = null): array
    {
        if ($domain === null) {
            return self::$events;
        }

        return array_values(array_filter(self::$events, static fn(array $event): bool => ($event['domain'] ?? null) === $domain));
    }

    public static function authEvidence(): array
    {
        return [
            'users' => count(self::$users),
            'credentials' => count(self::$credentials),
            'sessions' => count(self::$sessions),
            'roles' => count(self::$roles),
            'permissions' => count(self::$permissions),
            'policies' => count(self::$policies),
            'decisions' => count(self::$decisions),
            'event_cursor' => AdlaireEventLog::lastEventId(self::$events),
            'fingerprint' => self::fingerprint([
                'users' => self::$users,
                'credentials' => self::redactedCredentials(),
                'sessions' => self::$sessions,
                'roles' => self::$roles,
                'permissions' => self::$permissions,
                'policies' => self::$policies,
                'decisions' => self::$decisions,
            ]),
        ];
    }

    public static function authHealthSummary(): array
    {
        return [
            'ready' => self::policyConflictReport()['conflict'] === false,
            'users' => count(self::$users),
            'active_sessions' => count(array_filter(self::$sessions, static fn(array $session): bool => ($session['status'] ?? null) === 'active')),
            'event_count' => count(self::$events),
        ];
    }

    public static function authOperationalDashboard(): array
    {
        return [
            'health' => self::authHealthSummary(),
            'risk' => self::authRiskReport(),
            'production_gate' => self::authProductionReadinessGate(),
            'trust' => self::authTrustScore(),
        ];
    }

    public static function authControlTower(): array
    {
        return [
            'dashboard' => self::authOperationalDashboard(),
            'manual_review' => self::authManualReviewQueue(),
            'event_cursor' => AdlaireEventLog::lastEventId(self::$events),
        ];
    }

    public static function authOperatorActionChecklist(): array
    {
        return [
            'items' => ['review_policy_conflicts', 'review_failed_logins', 'review_revoked_sessions', 'review_manual_queue'],
            'required' => self::authManualReviewQueue()['count'] > 0,
        ];
    }

    public static function authOperationJournal(): array
    {
        return [
            'event_count' => count(self::$events),
            'latest_event_id' => AdlaireEventLog::lastEventId(self::$events),
            'will_mutate_event_log' => false,
        ];
    }

    public static function authIncidentTimeline(): array
    {
        return [
            'items' => array_values(array_filter(self::$events, static fn(array $event): bool => in_array($event['type'] ?? '', ['login_failure', 'access_deny', 'policy_revoke', 'session_revoke'], true))),
            'count' => count(self::$events),
        ];
    }

    public static function authIncidentSeverity(): array
    {
        $risk = self::authRiskReport();
        return ['severity' => $risk['risk_count'] === 0 ? 'low' : ($risk['risk_count'] > 3 ? 'high' : 'medium')];
    }

    public static function authIncidentEvidenceDigest(): array
    {
        return [
            'severity' => self::authIncidentSeverity()['severity'],
            'timeline_count' => self::authIncidentTimeline()['count'],
            'event_cursor' => AdlaireEventLog::lastEventId(self::$events),
        ];
    }

    public static function authIncidentContainment(): array
    {
        return [
            'recommended_action' => self::authRiskReport()['risk_count'] === 0 ? 'continue_observation' : 'manual_review',
            'automatic_recovery' => false,
        ];
    }

    public static function credentialExposureReport(): array
    {
        $revoked = count(array_filter(self::$credentials, static fn(array $credential): bool => ($credential['status'] ?? null) !== 'active'));
        return ['revoked_credentials' => $revoked, 'rotation_required' => false, 'automatic_rotation' => false];
    }

    public static function credentialTrustScore(): array
    {
        $total = max(1, count(self::$credentials));
        $active = count(array_filter(self::$credentials, static fn(array $credential): bool => ($credential['status'] ?? null) === 'active'));
        return ['score' => (int)floor(($active / $total) * 100)];
    }

    public static function sessionTrustScore(): array
    {
        $total = max(1, count(self::$sessions));
        $active = count(array_filter(self::$sessions, static fn(array $session): bool => ($session['status'] ?? null) === 'active'));
        return ['score' => (int)floor(($active / $total) * 100)];
    }

    public static function sessionAnomalyReport(): array
    {
        $items = [];
        foreach (self::$sessions as $session) {
            if (!isset(self::$users[$session['user_id']])) {
                $items[] = ['type' => 'missing_user', 'session_id' => $session['id']];
            }
        }
        return ['count' => count($items), 'items' => $items];
    }

    public static function sessionRecoveryPacket(): array
    {
        return [
            'sessions' => self::$sessions,
            'manual_review' => self::sessionAnomalyReport(),
            'automatic_recovery' => false,
        ];
    }

    public static function policyDriftReport(): array
    {
        $items = [];
        foreach (self::$policies as $policy) {
            if (!isset(self::$roles[$policy['role_id']], self::$permissions[$policy['permission_id']])) {
                $items[] = $policy;
            }
        }
        return ['drift' => $items !== [], 'items' => $items, 'count' => count($items)];
    }

    public static function policyBlastRadius(string $policyId): array
    {
        self::assertPolicy($policyId);
        $policy = self::$policies[$policyId];
        $permission = self::$permissions[$policy['permission_id']] ?? [];
        return [
            'subjects_affected' => [$policy['subject_id']],
            'resources_affected' => [$permission['resource'] ?? 'unknown'],
            'actions_affected' => [$permission['action'] ?? 'unknown'],
        ];
    }

    public static function permissionSaturationReport(): array
    {
        $counts = [];
        foreach (self::$policies as $policy) {
            $subject = (string)$policy['subject_id'];
            $counts[$subject] = ($counts[$subject] ?? 0) + 1;
        }
        return ['subjects' => $counts, 'saturated' => array_filter($counts, static fn(int $count): bool => $count > 10) !== []];
    }

    public static function accessDenialAnalysis(): array
    {
        $denied = array_values(array_filter(self::$decisions, static fn(array $decision): bool => ($decision['decision'] ?? null) === 'deny'));
        return ['count' => count($denied), 'items' => $denied];
    }

    public static function authorizationRecoveryPacket(): array
    {
        return [
            'policy_conflict' => self::policyConflictReport(),
            'policy_drift' => self::policyDriftReport(),
            'denials' => self::accessDenialAnalysis(),
        ];
    }

    public static function authAuditPacket(): array
    {
        return [
            'evidence' => self::authEvidence(),
            'policy_integrity' => self::policyIntegrityReport(),
            'session_integrity' => self::sessionIntegrityReport(),
            'event_cursor' => AdlaireEventLog::lastEventId(self::$events),
        ];
    }

    public static function authEvidenceSeal(): array
    {
        $evidence = self::authEvidence();
        return ['evidence' => $evidence, 'seal' => self::fingerprint($evidence), 'verified' => true];
    }

    public static function authTrustLedger(): array
    {
        return [
            'credential_trust' => self::credentialTrustScore(),
            'session_trust' => self::sessionTrustScore(),
            'policy_integrity' => self::policyIntegrityReport(),
            'decision_trace' => self::$decisions,
        ];
    }

    public static function authRecoveryEvidence(): array
    {
        return [
            'users' => self::$users,
            'sessions' => self::$sessions,
            'policies' => self::$policies,
            'decisions' => self::$decisions,
            'event_cursor' => AdlaireEventLog::lastEventId(self::$events),
        ];
    }

    public static function authManualReviewQueue(): array
    {
        $items = array_merge(self::policyConflictReport()['items'], self::sessionAnomalyReport()['items']);
        return ['count' => count($items), 'items' => $items, 'automatic_repair' => false];
    }

    public static function authProductionReadinessGate(): array
    {
        $ready = self::authManualReviewQueue()['count'] === 0 && self::policyIntegrityReport()['valid'] === true;
        return ['status' => $ready ? 'ready' : 'manual_review_required', 'ready' => $ready];
    }

    public static function authWriteSafetyGate(string $operation): array
    {
        return [
            'operation' => $operation,
            'allowed' => self::authProductionReadinessGate()['ready'],
            'automatic_block' => false,
        ];
    }

    public static function authEmergencyFreezeView(): array
    {
        return ['freeze_required' => self::authManualReviewQueue()['count'] > 0, 'automatic_freeze' => false];
    }

    public static function authDegradedModeView(): array
    {
        return ['mode' => self::authProductionReadinessGate()['ready'] ? 'normal' : 'degraded'];
    }

    public static function authRiskReport(): array
    {
        $risk = self::authManualReviewQueue()['count'] + self::accessDenialAnalysis()['count'];
        return ['risk_count' => $risk, 'status' => $risk === 0 ? 'clear' : 'review_required'];
    }

    public static function authTrustScore(): array
    {
        $risk = self::authRiskReport()['risk_count'];
        return ['score' => max(0, 100 - ($risk * 10))];
    }

    public static function sessionIntegrityReport(): array
    {
        return ['valid' => self::sessionAnomalyReport()['count'] === 0, 'sessions' => count(self::$sessions)];
    }

    public static function policyIntegrityReport(): array
    {
        return ['valid' => self::policyDriftReport()['drift'] === false && self::policyConflictReport()['conflict'] === false];
    }

    public static function authChangeImpactReport(string $changeType, array $change): array
    {
        $subjects = self::affectedSubjects($change);
        $sessions = array_values(array_filter(self::$sessions, static fn(array $session): bool => in_array((string)$session['user_id'], $subjects, true)));
        $sessionIds = array_map(static fn(array $session): string => (string)$session['id'], $sessions);
        $decisions = array_values(array_filter(self::$decisions, static fn(array $decision): bool => in_array((string)$decision['session_id'], $sessionIds, true)));

        return [
            'change_type' => $changeType,
            'affected_subjects' => $subjects,
            'affected_sessions' => $sessions,
            'affected_decisions' => $decisions,
            'automatic_change' => false,
            'fingerprint' => self::fingerprint(['change_type' => $changeType, 'change' => $change, 'subjects' => $subjects]),
        ];
    }

    public static function policySimulation(string $subjectId, string $resource, string $action, array $policyChanges = []): array
    {
        self::assertUser($subjectId);
        $policies = self::simulatedPolicies($policyChanges);
        $result = self::simulateDecision($policies, $subjectId, $resource, $action);

        return [
            'subject_id' => $subjectId,
            'resource' => $resource,
            'action' => $action,
            'decision' => $result['decision'],
            'reason' => $result['reason'],
            'policy_id' => $result['policy_id'],
            'dry_run' => true,
            'will_mutate' => false,
        ];
    }

    public static function sessionRevocationImpact(string $sessionId): array
    {
        self::assertSession($sessionId);
        $session = self::$sessions[$sessionId];
        $userId = (string)$session['user_id'];
        $sameUserSessions = array_values(array_filter(self::$sessions, static fn(array $item): bool => ($item['user_id'] ?? null) === $userId));
        $decisions = array_values(array_filter(self::$decisions, static fn(array $decision): bool => ($decision['session_id'] ?? null) === $sessionId));

        return [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'same_user_session_count' => count($sameUserSessions),
            'affected_decisions' => $decisions,
            'automatic_revoke' => false,
        ];
    }

    public static function credentialRevocationImpact(string $credentialId): array
    {
        self::assertCredential($credentialId);
        $credential = self::$credentials[$credentialId];
        $userId = (string)$credential['user_id'];
        $sessions = array_values(array_filter(self::$sessions, static fn(array $session): bool => ($session['user_id'] ?? null) === $userId));
        $loginEvents = array_values(array_filter(self::$events, static fn(array $event): bool => ($event['payload']['credential_id'] ?? null) === $credentialId));

        return [
            'credential_id' => $credentialId,
            'user_id' => $userId,
            'affected_sessions' => $sessions,
            'login_event_count' => count($loginEvents),
            'automatic_revoke' => false,
        ];
    }

    public static function permissionCoverageReport(): array
    {
        $items = [];
        foreach (self::$permissions as $permission) {
            $policies = array_values(array_filter(self::$policies, static fn(array $policy): bool => ($policy['permission_id'] ?? null) === $permission['id'] && ($policy['status'] ?? null) === 'active'));
            $items[] = [
                'permission_id' => $permission['id'],
                'resource' => $permission['resource'],
                'action' => $permission['action'],
                'policy_count' => count($policies),
                'subjects' => array_values(array_unique(array_map(static fn(array $policy): string => (string)$policy['subject_id'], $policies))),
            ];
        }

        return ['count' => count($items), 'items' => $items];
    }

    public static function unusedPermissionReport(): array
    {
        $items = array_values(array_filter(self::permissionCoverageReport()['items'], static fn(array $item): bool => $item['policy_count'] === 0));
        return ['count' => count($items), 'items' => $items];
    }

    public static function dormantUserReport(): array
    {
        $items = [];
        foreach (self::$users as $user) {
            $userId = (string)$user['id'];
            $hasCredential = self::hasMatching(self::$credentials, 'user_id', $userId);
            $hasSession = self::hasMatching(self::$sessions, 'user_id', $userId);
            $hasPolicy = self::hasMatching(self::$policies, 'subject_id', $userId);
            if (!$hasCredential && !$hasSession && !$hasPolicy) {
                $items[] = $user;
            }
        }

        return ['count' => count($items), 'items' => $items];
    }

    public static function staleSessionReport(): array
    {
        $validated = [];
        foreach (self::$events as $event) {
            if (($event['type'] ?? null) === 'session_validate') {
                $validated[(string)($event['payload']['session_id'] ?? '')] = true;
            }
        }
        $items = array_values(array_filter(self::$sessions, static fn(array $session): bool => ($session['status'] ?? null) === 'active' && !isset($validated[(string)$session['id']])));

        return ['count' => count($items), 'items' => $items, 'automatic_revoke' => false];
    }

    public static function failedLoginTrend(): array
    {
        $failures = array_values(array_filter(self::$events, static fn(array $event): bool => ($event['type'] ?? null) === 'login_failure'));
        $byCredential = [];
        $byReason = [];
        foreach ($failures as $event) {
            $credentialId = (string)($event['payload']['credential_id'] ?? 'unknown');
            $reason = (string)($event['payload']['reason'] ?? 'unknown');
            $byCredential[$credentialId] = ($byCredential[$credentialId] ?? 0) + 1;
            $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;
        }
        ksort($byCredential);
        ksort($byReason);

        return ['count' => count($failures), 'by_credential' => $byCredential, 'by_reason' => $byReason];
    }

    public static function accessPatternBaseline(): array
    {
        $patterns = [];
        foreach (self::$decisions as $decision) {
            $session = self::$sessions[$decision['session_id']] ?? [];
            $userId = (string)($session['user_id'] ?? 'unknown');
            $key = $userId . '|' . $decision['resource'] . '|' . $decision['action'] . '|' . $decision['decision'];
            $patterns[$key] = ($patterns[$key] ?? 0) + 1;
        }
        ksort($patterns);

        return [
            'patterns' => $patterns,
            'decision_count' => count(self::$decisions),
            'fingerprint' => self::fingerprint($patterns),
        ];
    }

    public static function accessPatternDriftReport(array $baseline): array
    {
        $current = self::accessPatternBaseline();
        $baselinePatterns = isset($baseline['patterns']) && is_array($baseline['patterns']) ? $baseline['patterns'] : [];
        $newPatterns = array_values(array_diff(array_keys($current['patterns']), array_keys($baselinePatterns)));
        $missingPatterns = array_values(array_diff(array_keys($baselinePatterns), array_keys($current['patterns'])));

        return [
            'drift' => $newPatterns !== [] || $missingPatterns !== [],
            'new_patterns' => $newPatterns,
            'missing_patterns' => $missingPatterns,
            'current' => $current,
        ];
    }

    public static function roleSaturationReport(): array
    {
        $items = [];
        foreach (self::$roles as $role) {
            $policies = array_values(array_filter(self::$policies, static fn(array $policy): bool => ($policy['role_id'] ?? null) === $role['id'] && ($policy['status'] ?? null) === 'active'));
            $items[] = [
                'role_id' => $role['id'],
                'name' => $role['name'],
                'policy_count' => count($policies),
                'subjects' => array_values(array_unique(array_map(static fn(array $policy): string => (string)$policy['subject_id'], $policies))),
                'review_required' => count($policies) > 10,
            ];
        }

        return ['count' => count($items), 'items' => $items];
    }

    public static function policyExpiryPlan(): array
    {
        $activePolicies = array_values(array_filter(self::$policies, static fn(array $policy): bool => ($policy['status'] ?? null) === 'active'));
        return [
            'candidate_count' => count($activePolicies),
            'items' => $activePolicies,
            'automatic_expiry' => false,
        ];
    }

    public static function emergencyAccessReview(): array
    {
        $adminDecisions = array_values(array_filter(self::$decisions, static fn(array $decision): bool => ($decision['action'] ?? null) === 'admin'));
        return [
            'admin_decision_count' => count($adminDecisions),
            'admin_decisions' => $adminDecisions,
            'manual_review' => self::authManualReviewQueue(),
            'automatic_privilege_escalation' => false,
        ];
    }

    public static function authEvidenceExport(): array
    {
        $payload = [
            'kind' => 'auth_evidence_export',
            'version' => self::VERSION,
            'evidence' => self::authEvidence(),
            'users' => self::$users,
            'credentials' => self::redactedCredentials(),
            'sessions' => self::$sessions,
            'roles' => self::$roles,
            'permissions' => self::$permissions,
            'policies' => self::$policies,
            'decisions' => self::$decisions,
            'events' => self::$events,
        ];
        $payload['fingerprint'] = self::fingerprint($payload);

        return $payload;
    }

    public static function authEvidenceImportValidation(array $payload): array
    {
        $errors = [];
        foreach (['kind', 'version', 'users', 'credentials', 'sessions', 'roles', 'permissions', 'policies', 'decisions', 'events', 'fingerprint'] as $key) {
            if (!array_key_exists($key, $payload)) {
                $errors[] = 'missing_' . $key;
            }
        }
        if (($payload['kind'] ?? null) !== 'auth_evidence_export') {
            $errors[] = 'invalid_kind';
        }
        $fingerprintPayload = $payload;
        $fingerprint = (string)($fingerprintPayload['fingerprint'] ?? '');
        unset($fingerprintPayload['fingerprint']);
        if ($fingerprint !== '' && self::fingerprint($fingerprintPayload) !== $fingerprint) {
            $errors[] = 'fingerprint_mismatch';
        }

        return ['valid' => $errors === [], 'errors' => $errors, 'dry_run' => true, 'will_mutate' => false];
    }

    public static function authStateCompare(array $before, array $after): array
    {
        $keys = ['users', 'credentials', 'sessions', 'roles', 'permissions', 'policies', 'decisions', 'events'];
        $diff = [];
        foreach ($keys as $key) {
            $beforeCount = isset($before[$key]) && is_array($before[$key]) ? count($before[$key]) : 0;
            $afterCount = isset($after[$key]) && is_array($after[$key]) ? count($after[$key]) : 0;
            $diff[$key] = ['before' => $beforeCount, 'after' => $afterCount, 'delta' => $afterCount - $beforeCount];
        }

        return ['changed' => array_filter($diff, static fn(array $item): bool => $item['delta'] !== 0) !== [], 'diff' => $diff];
    }

    public static function authorizationRegressionGuard(array $baseline): array
    {
        $baselinePatterns = isset($baseline['patterns']) && is_array($baseline['patterns']) ? $baseline['patterns'] : [];
        $currentPatterns = self::accessPatternBaseline()['patterns'];
        $regressions = [];
        foreach ($baselinePatterns as $pattern => $count) {
            if (!isset($currentPatterns[$pattern])) {
                $regressions[] = ['type' => 'missing_access_pattern', 'pattern' => $pattern, 'baseline_count' => $count];
            }
        }

        return ['passed' => $regressions === [], 'regressions' => $regressions, 'count' => count($regressions)];
    }

    public static function authOperationsLedger(): array
    {
        return [
            'event_count' => count(self::$events),
            'latest_event_id' => AdlaireEventLog::lastEventId(self::$events),
            'items' => array_map(static fn(array $event): array => [
                'id' => $event['id'] ?? null,
                'sequence' => $event['sequence'] ?? null,
                'domain' => $event['domain'] ?? null,
                'type' => $event['type'] ?? null,
                'record_id' => $event['record_id'] ?? null,
            ], self::$events),
        ];
    }

    public static function authControlSummary(): array
    {
        return [
            'readiness' => self::readiness(),
            'health' => self::authHealthSummary(),
            'manual_review' => self::authManualReviewQueue(),
            'failed_login_trend' => self::failedLoginTrend(),
            'permission_coverage' => self::permissionCoverageReport(),
            'unused_permissions' => self::unusedPermissionReport(),
            'stale_sessions' => self::staleSessionReport(),
            'operations_ledger' => self::authOperationsLedger(),
            'automatic_recovery' => false,
        ];
    }

    private static function decision(string $sessionId, ?array $policy, string $resource, string $action, bool $allowed, string $reason): array
    {
        $decision = [
            'id' => self::nextId('decision', ++self::$decisionSequence),
            'session_id' => $sessionId,
            'policy_id' => $policy['id'] ?? null,
            'resource' => $resource,
            'action' => $action,
            'decision' => $allowed ? 'allow' : 'deny',
            'reason' => $reason,
        ];
        self::$decisions[$decision['id']] = $decision;
        self::recordAuthEvent('authorization', $allowed ? 'access_allow' : 'access_deny', $decision['id'], $decision);

        return $decision;
    }

    private static function affectedSubjects(array $change): array
    {
        $subjects = [];
        foreach (['subject_id', 'user_id'] as $key) {
            if (isset($change[$key]) && is_string($change[$key])) {
                $subjects[] = $change[$key];
            }
        }
        if (isset($change['session_id'], self::$sessions[$change['session_id']])) {
            $subjects[] = (string)self::$sessions[$change['session_id']]['user_id'];
        }
        if (isset($change['credential_id'], self::$credentials[$change['credential_id']])) {
            $subjects[] = (string)self::$credentials[$change['credential_id']]['user_id'];
        }
        if (isset($change['policy_id'], self::$policies[$change['policy_id']])) {
            $subjects[] = (string)self::$policies[$change['policy_id']]['subject_id'];
        }
        if (isset($change['role_id'])) {
            foreach (self::$policies as $policy) {
                if (($policy['role_id'] ?? null) === $change['role_id']) {
                    $subjects[] = (string)$policy['subject_id'];
                }
            }
        }
        if (isset($change['permission_id'])) {
            foreach (self::$policies as $policy) {
                if (($policy['permission_id'] ?? null) === $change['permission_id']) {
                    $subjects[] = (string)$policy['subject_id'];
                }
            }
        }

        return array_values(array_unique(array_filter($subjects)));
    }

    private static function simulatedPolicies(array $policyChanges): array
    {
        $policies = self::$policies;
        foreach ($policyChanges as $change) {
            if (!is_array($change)) {
                continue;
            }
            $type = (string)($change['type'] ?? 'assign');
            if ($type === 'revoke' && isset($change['policy_id'], $policies[$change['policy_id']])) {
                $policies[$change['policy_id']]['status'] = 'revoked';
                continue;
            }
            if (isset($change['subject_id'], $change['role_id'], $change['permission_id'])) {
                $id = (string)($change['id'] ?? ('sim_policy_' . count($policies)));
                $policies[$id] = [
                    'id' => $id,
                    'subject_id' => (string)$change['subject_id'],
                    'role_id' => (string)$change['role_id'],
                    'permission_id' => (string)$change['permission_id'],
                    'effect' => in_array(($change['effect'] ?? 'allow'), ['allow', 'deny'], true) ? (string)$change['effect'] : 'allow',
                    'status' => (string)($change['status'] ?? 'active'),
                ];
            }
        }

        return $policies;
    }

    private static function simulateDecision(array $policies, string $subjectId, string $resource, string $action): array
    {
        foreach ($policies as $policy) {
            if (($policy['subject_id'] ?? null) !== $subjectId || ($policy['status'] ?? null) !== 'active') {
                continue;
            }
            $role = self::$roles[$policy['role_id']] ?? null;
            $permission = self::$permissions[$policy['permission_id']] ?? null;
            if (($role['status'] ?? null) !== 'active') {
                return ['decision' => 'deny', 'reason' => 'inactive_role', 'policy_id' => $policy['id'] ?? null];
            }
            if (($permission['status'] ?? null) !== 'active') {
                return ['decision' => 'deny', 'reason' => 'inactive_permission', 'policy_id' => $policy['id'] ?? null];
            }
            if (($permission['resource'] ?? null) === $resource && ($permission['action'] ?? null) === $action) {
                $allowed = ($policy['effect'] ?? null) === 'allow';
                return ['decision' => $allowed ? 'allow' : 'deny', 'reason' => $allowed ? 'matched_policy' : 'explicit_deny', 'policy_id' => $policy['id'] ?? null];
            }
        }

        return ['decision' => 'deny', 'reason' => 'no_policy', 'policy_id' => null];
    }

    private static function hasMatching(array $items, string $field, string $value): bool
    {
        foreach ($items as $item) {
            if (($item[$field] ?? null) === $value) {
                return true;
            }
        }

        return false;
    }

    private static function assertStatus(string $status): void
    {
        if (!in_array($status, ['active', 'inactive', 'revoked'], true)) {
            throw new InvalidArgumentException('Unsupported auth status.');
        }
    }

    private static function assertUser(string $userId): void
    {
        if (!isset(self::$users[$userId])) {
            throw new InvalidArgumentException('Unknown user.');
        }
    }

    private static function assertCredential(string $credentialId): void
    {
        if (!isset(self::$credentials[$credentialId])) {
            throw new InvalidArgumentException('Unknown credential.');
        }
    }

    private static function assertSession(string $sessionId): void
    {
        if (!isset(self::$sessions[$sessionId])) {
            throw new InvalidArgumentException('Unknown session.');
        }
    }

    private static function assertRole(string $roleId): void
    {
        if (!isset(self::$roles[$roleId])) {
            throw new InvalidArgumentException('Unknown role.');
        }
    }

    private static function assertPermission(string $permissionId): void
    {
        if (!isset(self::$permissions[$permissionId])) {
            throw new InvalidArgumentException('Unknown permission.');
        }
    }

    private static function assertPolicy(string $policyId): void
    {
        if (!isset(self::$policies[$policyId])) {
            throw new InvalidArgumentException('Unknown policy.');
        }
    }

    private static function redactCredential(array $credential): array
    {
        unset($credential['secret_hash']);
        $credential['secret_hash_stored'] = true;
        return $credential;
    }

    private static function redactedCredentials(): array
    {
        return array_map(static fn(array $credential): array => self::redactCredential($credential), self::$credentials);
    }
}
