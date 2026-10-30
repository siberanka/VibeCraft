<?php

namespace App\Core\Security;

/**
 * Permission catalogue. Finance permissions are deliberately separate from support
 * and moderation permissions (a ticket role never grants refunds).
 */
final class Permissions
{
    public const ALL = [
        'admin.access' => 'Yönetim paneline erişim',
        'users.view' => 'Kullanıcıları görüntüleme',
        'users.manage' => 'Kullanıcı düzenleme',
        'users.ban' => 'Kullanıcı yasaklama',
        'roles.manage' => 'Rol ve yetki yönetimi',
        'store.manage' => 'Ürün ve kategori yönetimi',
        'coupons.manage' => 'Kupon yönetimi',
        'orders.view' => 'Siparişleri görüntüleme',
        'orders.manage' => 'Sipariş iptali / not',
        'finance.refund' => 'İade işaretleme (finans)',
        'finance.wallet_adjust' => 'Cüzdan düzeltmesi (finans)',
        'deliveries.manage' => 'Teslimat kuyruğu yönetimi',
        'servers.manage' => 'Sunucu ve köprü yönetimi',
        'content.manage' => 'Haber ve sayfa yönetimi',
        'tickets.view' => 'Destek taleplerini görüntüleme',
        'tickets.reply' => 'Destek taleplerine yanıt',
        'settings.manage' => 'Genel ayarlar',
        'appearance.manage' => 'Tema ve görünüm',
        'integrations.manage' => 'Entegrasyon sırları (ödeme, CAPTCHA, Discord)',
        'forum.moderate' => 'Forum moderasyonu',
        'applications.review' => 'Yetkili başvurularını değerlendirme',
        'audit.view' => 'Denetim günlüğü',
        'system.view' => 'Sistem sağlığı',
    ];

    public const ROLES = [
        'owner' => '*',
        'admin' => [
            'admin.access', 'users.view', 'users.manage', 'users.ban', 'store.manage', 'coupons.manage', 'orders.view',
            'orders.manage', 'finance.refund', 'deliveries.manage', 'servers.manage', 'content.manage', 'tickets.view',
            'tickets.reply', 'settings.manage', 'appearance.manage', 'audit.view', 'system.view', 'applications.review', 'forum.moderate',
        ],
        'moderator' => ['admin.access', 'users.view', 'users.ban', 'content.manage', 'tickets.view', 'tickets.reply', 'applications.review', 'forum.moderate'],
        'support' => ['admin.access', 'users.view', 'orders.view', 'tickets.view', 'tickets.reply'],
        'member' => [],
    ];
}
