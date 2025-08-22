<?php

namespace App\Services;

use App\Infrastructure\Database\Database;

class ReferralService
{
	private Database $db;

	public function __construct(Database $db)
	{
		$this->db = $db;
	}

	public function createPending(int $inviterUserId, int $inviteeUserId): void
	{
		if ($inviterUserId === $inviteeUserId) {
			return; // no self referral
		}
		// avoid duplicate
		$exists = $this->db->fetchOne('SELECT id FROM referrals WHERE inviter_user_id = ? AND invitee_user_id = ?', [$inviterUserId, $inviteeUserId]);
		if ($exists) {
			return;
		}
		$this->db->insert('INSERT INTO referrals (inviter_user_id, invitee_user_id) VALUES (?, ?)', [$inviterUserId, $inviteeUserId]);
	}

	public function qualifyIfPending(int $inviteeUserId, int $pointsPerReferral): ?array
	{
		$ref = $this->db->fetchOne("SELECT r.*, u.points as inviter_points FROM referrals r JOIN users i ON i.id = r.invitee_user_id JOIN users u ON u.id = r.inviter_user_id WHERE r.invitee_user_id = ? AND r.status = 'pending'", [$inviteeUserId]);
		if (!$ref) {
			return null;
		}
		$this->db->execute("UPDATE referrals SET status = 'qualified', qualified_at = NOW() WHERE id = ?", [$ref['id']]);
		return $ref;
	}
}