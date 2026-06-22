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
