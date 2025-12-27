<?php
$page_title = 'Tiket Saya';
require_once '../config/config.php';
require_login();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get user's tickets (paid bookings only)
$tickets = $conn->query("SELECT r.*, s.service_name, s.route, s.departure_time, s.arrival_time,
    cr.status as cancel_status, cr.processed_at as cancel_processed_at
    FROM reservations r 
    JOIN services s ON r.service_id = s.id 
    LEFT JOIN cancellation_requests cr ON r.id = cr.reservation_id AND cr.status = 'approved'
    WHERE r.user_id = $user_id AND r.payment_status = 'paid'
    ORDER BY r.travel_date DESC, r.created_at DESC");

include '../includes/header.php';
?>

<style>
    /* Tickets grid for desktop */
    .tickets-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
        padding: 1.5rem;
    }

    /* Ticket card styles */
    .ticket-card {
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .ticket-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
    }

    /* Refund status badges */
    .refund-badge {
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
        text-align: center;
        animation: fadeIn 0.3s ease-in;
    }

    .refund-processing {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fbbf24;
    }

    .refund-completed {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #10b981;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Status badges container */
    .status-badges-container {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        align-items: flex-end;
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
        .tickets-grid {
            grid-template-columns: 1fr !important;
            padding: 1rem !important;
            gap: 1rem !important;
        }

        .ticket-card {
            width: 100% !important;
            max-width: 100% !important;
        }

        /* Mobile ticket card header adjustments */
        .ticket-card > div:first-child {
            padding-bottom: 1rem !important;
        }
        
        .ticket-card > div:first-child > div:first-child > div:first-child > div:nth-child(2) {
            font-size: 1.05rem !important; 
            letter-spacing: 0.5px !important; 
            word-break: break-all;
        }

        .ticket-card > div:first-child > div:first-child {
            flex-direction: column !important;
            align-items: flex-start !important;
        }

        .ticket-card > div:first-child > div:first-child > div:first-child {
            margin-bottom: 0.75rem;
            width: 100%;
        }

        /* Stack badges vertically on mobile */
        .status-badges-container {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
            width: 100%;
        }

        .ticket-card > div:first-child > div:first-child > .status-badges-container {
            position: static !important;
            margin-top: 0 !important;
            align-self: flex-start;
        }
    }

    @media (max-width: 480px) {
        .tickets-grid {
            padding: 0.75rem !important;
        }

        .ticket-card > div:first-child > div:first-child > div:first-child > div:nth-child(2) {
            font-size: 0.95rem !important;
            letter-spacing: 0.3px !important;
            word-break: break-all;
        }

        /* Smaller refund badges on very small screens */
        .refund-badge {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
        }
    }
</style>

<section style="padding: 2rem 0; background: var(--light);">
    <div class="container">
        <h1><i class="fas fa-ticket-alt"></i> Tiket Saya</h1>
        <p style="color: var(--gray);">Lihat dan kelola tiket perjalanan Anda</p>
    </div>
</section>

<section style="padding: 2rem 0;">
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-ticket-alt"></i> Tiket Aktif</h3>
            </div>

            <?php if ($tickets->num_rows > 0): ?>
                <div class="tickets-grid">
                    <?php while ($ticket = $tickets->fetch_assoc()):
                        $passenger_names = json_decode($ticket['passenger_names'], true);
                        $is_upcoming = strtotime($ticket['travel_date']) >= strtotime(date('Y-m-d'));
                        $is_past = strtotime($ticket['travel_date']) < strtotime(date('Y-m-d'));
                        $is_cancelled = $ticket['booking_status'] === 'cancelled';
                        
                        // Calculate refund status
                        $refund_status = 'none';
                        $cancel_timestamp = null;
                        if ($is_cancelled && $ticket['cancel_processed_at']) {
                            $cancel_timestamp = strtotime($ticket['cancel_processed_at']);
                            $current_timestamp = time();
                            $time_diff_minutes = ($current_timestamp - $cancel_timestamp) / 60;
                            
                            if ($time_diff_minutes >= 2) {
                                $refund_status = 'completed';
                            } else {
                                $refund_status = 'processing';
                            }
                        }
                    ?>
                        <div class="ticket-card" 
                             style="border: 2px solid <?php echo $is_cancelled ? '#dc2626' : ($is_upcoming ? 'var(--primary)' : 'var(--gray)'); ?>; border-radius: 12px; overflow: hidden; background: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); <?php echo $is_cancelled ? 'opacity: 0.85;' : ''; ?>"
                             data-cancelled="<?php echo $is_cancelled ? '1' : '0'; ?>"
                             data-cancel-timestamp="<?php echo $cancel_timestamp ?? ''; ?>"
                             data-refund-status="<?php echo $refund_status; ?>">
                            
                            <!-- Ticket Header -->
                            <div style="background: <?php echo $is_cancelled ? '#dc2626' : ($is_upcoming ? 'linear-gradient(135deg, var(--primary), #1d4ed8)' : 'var(--gray)'); ?>; color: white; padding: 1rem; position: relative;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                    <div>
                                        <div style="font-size: 0.85rem; opacity: 0.9;">Kode Booking</div>
                                        <div style="font-size: 1.3rem; font-weight: bold; letter-spacing: 1px;">
                                            <?php echo $ticket['booking_code']; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Status Badges Container -->
                                    <div class="status-badges-container">
                                        <!-- Refund Status Badge -->
                                        <?php if ($is_cancelled && $refund_status !== 'none'): ?>
                                            <span class="refund-badge refund-<?php echo $refund_status; ?>" 
                                                  id="refund-badge-<?php echo $ticket['id']; ?>">
                                                <?php if ($refund_status === 'processing'): ?>
                                                    <i class="fas fa-spinner fa-spin"></i> Refund Processing
                                                <?php else: ?>
                                                    <i class="fas fa-check-circle"></i> Refunded
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <!-- Cancelled/Upcoming/Past Badge -->
                                        <?php if ($is_cancelled): ?>
                                            <span style="background: rgba(255,255,255,0.95); color: #dc2626; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                                <i class="fas fa-times-circle"></i> Cancelled
                                            </span>
                                        <?php elseif ($is_upcoming): ?>
                                            <span style="background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem;">
                                                <i class="fas fa-clock"></i> Upcoming
                                            </span>
                                        <?php else: ?>
                                            <span style="background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem;">
                                                <i class="fas fa-history"></i> Past
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($ticket['checked_in'] && !$is_cancelled): ?>
                                    <div style="position: absolute; bottom: 10px; right: 10px;">
                                        <span style="background: var(--secondary); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem;">
                                            <i class="fas fa-check-circle"></i> Checked In
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Ticket Body -->
                            <div style="padding: 1.5rem; <?php echo $is_cancelled ? 'position: relative;' : ''; ?>">
                                <?php if ($is_cancelled): ?>
                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-15deg); font-size: 4rem; font-weight: bold; color: rgba(220, 38, 38, 0.06); pointer-events: none; z-index: 1; white-space: nowrap;">
                                        CANCELLED
                                    </div>
                                <?php endif; ?>

                                <div style="position: relative; z-index: 2;">
                                    <!-- Service Info -->
                                    <div style="margin-bottom: 1.5rem;">
                                        <h4 style="color: <?php echo $is_cancelled ? '#dc2626' : 'var(--primary)'; ?>; margin-bottom: 0.5rem; font-size: 1.1rem; <?php echo $is_cancelled ? 'text-decoration: line-through;' : ''; ?>">
                                            <?php echo $ticket['service_name']; ?>
                                        </h4>
                                        <div style="color: var(--gray); font-size: 0.9rem;">
                                            <i class="fas fa-route"></i> <?php echo $ticket['route']; ?>
                                        </div>
                                    </div>

                                    <!-- Travel Date & Time -->
                                    <div style="background: <?php echo $is_cancelled ? '#fee2e2' : 'var(--light)'; ?>; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                            <div>
                                                <div style="font-size: 0.8rem; color: var(--gray); margin-bottom: 0.25rem;">Tanggal</div>
                                                <div style="font-weight: 600; color: var(--dark);">
                                                    <?php echo format_date($ticket['travel_date']); ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div style="font-size: 0.8rem; color: var(--gray); margin-bottom: 0.25rem;">Waktu</div>
                                                <div style="font-weight: 600; color: var(--dark);">
                                                    <?php echo date('H:i', strtotime($ticket['departure_time'])); ?> - <?php echo date('H:i', strtotime($ticket['arrival_time'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Passengers -->
                                    <div style="margin-bottom: 1rem;">
                                        <div style="font-size: 0.95rem; color: var(--gray); margin-bottom: 0.5rem;">
                                            <i class="fas fa-users"></i> <?php echo count($passenger_names); ?> Penumpang
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--dark);">
                                            <?php echo htmlspecialchars(implode(', ', array_slice($passenger_names, 0, 3))); ?>
                                            <?php if (count($passenger_names) > 3): ?>
                                                <span style="color: var(--gray);"> +<?php echo count($passenger_names) - 3; ?> lainnya</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Price -->
                                    <div style="border-top: 2px dashed var(--light); padding-top: 1rem; margin-bottom: 1rem;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span style="color: var(--gray); font-size: 0.9rem;">Total Bayar</span>
                                            <span style="font-size: 1.2rem; font-weight: bold; color: <?php echo $is_cancelled ? '#dc2626' : 'var(--primary)'; ?>; <?php echo $is_cancelled ? 'text-decoration: line-through;' : ''; ?>">
                                                <?php echo format_currency($ticket['total_price']); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <?php if ($is_cancelled): ?>
                                        <div style="text-align: center; padding: 1rem; background: #fee2e2; border-radius: 8px; border-left: 4px solid #dc2626;">
                                            <div style="color: #dc2626; font-weight: 600; margin-bottom: 0.5rem;">
                                                <i class="fas fa-ban"></i> Tiket Ini Telah Dibatalkan
                                            </div>
                                            <small style="color: #991b1b; display: block;" id="refund-message-<?php echo $ticket['id']; ?>">
                                                <?php if ($refund_status === 'processing'): ?>
                                                    Pembatalan diproses. Dana akan dikembalikan dalam 2 menit...
                                                <?php else: ?>
                                                    Pembatalan diproses dan dana telah dikembalikan
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                            <a href="<?php echo SITE_URL; ?>/user/ticket.php?booking=<?php echo $ticket['booking_code']; ?>"
                                                class="btn btn-primary"
                                                style="flex: 1; text-align: center; font-size: 0.9rem; padding: 0.75rem; min-width: 120px;">
                                                <i class="fas fa-ticket-alt"></i> Lihat Tiket
                                            </a>
                                            <?php if ($is_upcoming && !$ticket['checked_in']): ?>
                                                <a href="<?php echo SITE_URL; ?>/user/check-in.php?booking=<?php echo $ticket['booking_code']; ?>"
                                                    class="btn btn-secondary"
                                                    style="flex: 1; text-align: center; font-size: 0.9rem; padding: 0.75rem; min-width: 120px;">
                                                    <i class="fas fa-check"></i> Check-in
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: var(--gray);">
                    <i class="fas fa-ticket-alt" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">Belum ada tiket</p>
                    <p style="font-size: 0.9rem;">Tiket Anda akan muncul di sini setelah pembayaran berhasil</p>
                    <a href="<?php echo SITE_URL; ?>/user/reservation.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i> Pesan Tiket Sekarang
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
    // Refund status update function
    function updateRefundStatus() {
        const ticketCards = document.querySelectorAll('.ticket-card[data-cancelled="1"]');
        
        ticketCards.forEach(card => {
            const cancelTimestamp = parseInt(card.getAttribute('data-cancel-timestamp'));
            const currentRefundStatus = card.getAttribute('data-refund-status');
            
            if (cancelTimestamp && currentRefundStatus === 'processing') {
                const currentTime = Math.floor(Date.now() / 1000);
                const timeDiffMinutes = (currentTime - cancelTimestamp) / 60;
                
                // Check if 2 minutes have passed
                if (timeDiffMinutes >= 2) {
                    const refundBadge = card.querySelector('[id^="refund-badge-"]');
                    const refundMessage = card.querySelector('[id^="refund-message-"]');
                    
                    if (refundBadge) {
                        // Update badge
                        refundBadge.classList.remove('refund-processing');
                        refundBadge.classList.add('refund-completed');
                        refundBadge.innerHTML = '<i class="fas fa-check-circle"></i> Refunded';
                        
                        // Update card attribute
                        card.setAttribute('data-refund-status', 'completed');
                    }
                    
                    if (refundMessage) {
                        // Update message
                        refundMessage.textContent = 'Pembatalan diproses dan dana telah dikembalikan';
                    }
                }
            }
        });
    }

    // Check refund status every 10 seconds
    setInterval(updateRefundStatus, 10000);

    // Initial check on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateRefundStatus();
    });
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

include '../includes/footer.php';
?>