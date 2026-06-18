<?php

use Vimatech\Invitation\Enums\InvitationStatus;

it('has the correct values', function () {
    expect(InvitationStatus::Pending->value)->toBe('pending');
    expect(InvitationStatus::Accepted->value)->toBe('accepted');
    expect(InvitationStatus::Declined->value)->toBe('declined');
    expect(InvitationStatus::Expired->value)->toBe('expired');
    expect(InvitationStatus::Cancelled->value)->toBe('cancelled');
});

it('has labels', function () {
    expect(InvitationStatus::Pending->label())->toBe('Pending');
    expect(InvitationStatus::Accepted->label())->toBe('Accepted');
    expect(InvitationStatus::Declined->label())->toBe('Declined');
});
