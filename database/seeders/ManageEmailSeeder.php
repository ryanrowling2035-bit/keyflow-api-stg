<?php

namespace Database\Seeders;

use App\Constants\Constants;
use App\Models\ManageEmail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ManageEmailSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('manage_emails')->truncate();

        $emails = [
            [
                'title' => 'Enquiry Received',
                'slug' => Constants::EMAIL_SLUGS['enquiry']['enquiry_sent_details'],
                'subject' => 'Thank you for your enquiry',
                'emailContent' => '
                    <p>Hello {{name}},</p>
                    <p>Thank you for your enquiry. We have received your request and our team is reviewing it.</p>
                    <p>We will get back to you with the relevant details as soon as possible.</p>
                    <p>Email: {{email}}</p>
                    <p>Regards,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{name}}, {{email}}, {{company_name}}',
            ],
            [
                'title' => 'New Enquiry Assigned Agent',
                'slug' => Constants::EMAIL_SLUGS['enquiry']['new_enquiry_agent'],
                'subject' => 'New Enquiry Assigned - {{enquiryName}} - {{enquiryId}}',
                'emailContent' => '
                    <p>Hello {{agentName}},</p>
                    <p>A new enquiry has been assigned to you.</p>
                    <p>
                        <strong>Enquiry:</strong> {{enquiryName}}<br>
                        <strong>Reference:</strong> {{enquiryId}}<br>
                        <strong>Type:</strong> {{enquiryType}}<br>
                        <strong>Contact:</strong> {{contactName}}<br>
                        <strong>Assigned By:</strong> {{assignedBy}}<br>
                        <strong>Date:</strong> {{assignedDate}}
                    </p>
                    <p>
                        <a href="{{enquiryLink}}" style="display:inline-block;padding:12px 24px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            View Enquiry
                        </a>
                    </p>
                    <p>Thank you,<br><strong>{{companyName}}</strong></p>
                ',
                'macro' => '{{agentName}}, {{enquiryName}}, {{enquiryId}}, {{enquiryType}}, {{contactName}}, {{assignedBy}}, {{assignedDate}}, {{enquiryLink}}, {{companyName}}',
            ],
            [
                'title' => 'Forgot Password',
                'slug' => Constants::EMAIL_SLUGS['user']['forgot_password'],
                'subject' => 'Reset Your Password',
                'emailContent' => '
                    <p>Hello {{name}},</p>
                    <p>You requested to reset your password.</p>
                    <p>
                        <a href="{{reset_link}}" style="display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:5px;">
                            Reset Password
                        </a>
                    </p>
                    <p>If you did not request this, please ignore this email.</p>
                    <p>Thanks,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{name}}, {{reset_link}}, {{company_name}}',
            ],
            [
                'title' => 'Property Owner Listing Details',
                'slug' => Constants::EMAIL_SLUGS['property']['sent_property_details'],
                'subject' => 'Your Property Listing Details',
                'emailContent' => '
                    <p>Dear {{fullName}},</p>

                    <p>Thank you for listing your property with {{companyName}}.</p>

                    <p>We are pleased to share the complete details of your property listing for your reference.</p>

                    <hr style="border: 1px solid #e5e7eb; margin: 20px 0;">

                    <p><strong>📌 Property Information:</strong></p>
                    <p>
                        <strong>Reference Number:</strong> {{referenceNumber}}<br>
                        <strong>Property Title:</strong> {{propertyTitle}}<br>
                        <strong>City:</strong> {{city}}<br>
                        <strong>Community:</strong> {{community}}<br>
                        <strong>Sub Community:</strong> {{subCommunity}}<br>
                        <strong>Tower:</strong> {{tower}}<br>
                        <strong>Status:</strong> {{listingStatus}}<br>
                        <strong>Listing Price:</strong> {{listingPrice}}
                    </p>

                    <hr style="border: 1px solid #e5e7eb; margin: 20px 0;">

                    <p><strong>📎 Attached Documents:</strong></p>
                    {{attachmentList}}

                    <hr style="border: 1px solid #e5e7eb; margin: 20px 0;">

                    <p>All relevant documents are attached to this email for your records.</p>

                    <p>If you have any questions or would like to make changes to your listing, please contact us at {{companyEmail}} or call {{companyPhone}}.</p>

                    <p>Best regards,<br>
                    <strong>{{companyName}}</strong></p>
                ',
                'macro' => '{{fullName}}, {{companyName}}, {{referenceNumber}}, {{propertyTitle}}, {{city}}, {{community}}, {{subCommunity}}, {{tower}}, {{listingStatus}}, {{listingPrice}}, {{attachmentList}}, {{companyEmail}}, {{companyPhone}}',
            ],
            [
                'title' => 'Property Details Sent to Contact',
                'slug' => Constants::EMAIL_SLUGS['property']['sent_property_details_contact'],
                'subject' => 'Property Details – {{propertyTitle}}',
                'emailContent' => '
                    <p>Dear {{fullName}},</p>

                    <p>We are pleased to share the following property details from {{companyName}} for your review.</p>

                    <hr style="border: 1px solid #e5e7eb; margin: 20px 0;">

                    <p><strong>📌 Property Information:</strong></p>
                    <p>
                        <strong>Reference Number:</strong> {{referenceNumber}}<br>
                        <strong>Property Title:</strong> {{propertyTitle}}<br>
                        <strong>City:</strong> {{city}}<br>
                        <strong>Community:</strong> {{community}}<br>
                        <strong>Sub Community:</strong> {{subCommunity}}<br>
                        <strong>Tower:</strong> {{tower}}<br>
                        <strong>Status:</strong> {{listingStatus}}<br>
                        <strong>Listing Price:</strong> {{listingPrice}}
                    </p>

                    <hr style="border: 1px solid #e5e7eb; margin: 20px 0;">

                    <p><strong>📎 Attached Documents:</strong></p>
                    {{attachmentList}}

                    <hr style="border: 1px solid #e5e7eb; margin: 20px 0;">

                    <p>All relevant documents are attached to this email for your reference.</p>

                    <p>If you have any questions, please contact us at {{companyEmail}} or call {{companyPhone}}.</p>

                    <p>Best regards,<br>
                    <strong>{{companyName}}</strong></p>
                ',
                'macro' => '{{fullName}}, {{companyName}}, {{referenceNumber}}, {{propertyTitle}}, {{city}}, {{community}}, {{subCommunity}}, {{tower}}, {{listingStatus}}, {{listingPrice}}, {{attachmentList}}, {{companyEmail}}, {{companyPhone}}',
            ],
            [
                'title' => 'Welcome to Exclusive Links - Account Created',
                'slug' => Constants::EMAIL_SLUGS['user']['account_created'],
                'subject' => 'Welcome to Exclusive Links - Your Account Has Been Created',
                'emailContent' => '
                   <p>Hi {{name}},</p>
                    <p>Welcome to the <strong>Exclusive Links CRM system</strong>.</p>
                    <p>Your account has been successfully created by the Administrator.</p>
                    <p>For security reasons, you are required to reset your password before logging in for the first time. Please click the link below to set your password and activate your account.</p>
                    <p>
                        <strong>Reset Password URL:</strong> <a href="{{reset_password_url}}" target="_blank">{{reset_password_url}}</a><br>
                    </p>
                    <p>If you experience any issues accessing your account, please contact the Administrator or the IT support team for assistance.</p>
                    <p>We look forward to your active participation on the platform.</p>
                    <p>Kind regards,<br>
                    <strong>Exclusive Links Team</strong></p>
                ',
                'macro' => '{{name}}, {{email}}, {{reset_password_url}}, {{company_name}}',
            ],
            [
                'title' => 'Data Export Alert',
                'slug' => Constants::EMAIL_SLUGS['user']['data_export'],
                'subject' => 'Data Export Alert - {{module}} ({{record_count}} records)',
                'emailContent' => '
                    <p>Hello {{recipient_name}},</p>
                    <p>A data export has been performed on the CRM.</p>
                    <p>
                        <strong>User:</strong> {{user}}<br>
                        <strong>Module:</strong> {{module}}<br>
                        <strong>Records Exported:</strong> {{record_count}}<br>
                        <strong>Date/Time:</strong> {{exported_at}}
                    </p>
                    <p>If this export was not expected, please review the user\'s access.</p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{recipient_name}}, {{user}}, {{module}}, {{record_count}}, {{exported_at}}, {{company_name}}',
            ],
            [
                'title' => 'RERA Permit Request',
                'slug' => Constants::EMAIL_SLUGS['property']['rera_permit_request'],
                'subject' => 'RERA Permit Request - {{property_title}} - Requested by {{requested_by}}',
                'emailContent' => '
                    <p>Hello {{admin_name}},</p>
                    <p>A new RERA permit request has been submitted.</p>
                    <p>
                        <strong>Property Title:</strong> {{property_title}}<br>
                        <strong>Reference Number:</strong> {{property_id}}<br>
                        <strong>Listing Type:</strong> {{listing_type}}<br>
                        <strong>City:</strong> {{city}}<br>
                        <strong>Community:</strong> {{community}}<br>
                        <strong>Sub Community:</strong> {{sub_community}}<br>
                        <strong>Tower:</strong> {{tower}}<br>
                        <strong>Requested By:</strong> {{requested_by}}<br>
                        <strong>Requested Date:</strong> {{requested_date}}
                    </p>
                    <p>
                        <a href="{{property_link}}" style="display:inline-block;padding:12px 24px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            View Property
                        </a>
                    </p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{admin_name}}, {{property_title}}, {{property_id}}, {{listing_type}}, {{city}}, {{community}}, {{sub_community}}, {{tower}}, {{requested_by}}, {{requested_date}}, {{property_link}}, {{company_name}}',
            ],
            [
                'title' => 'RERA Manager Approval',
                'slug' => Constants::EMAIL_SLUGS['property']['rera_manager_approval'],
                'subject' => 'Approval Required - {{property_title}} - {{property_id}}',
                'emailContent' => '
                    <p>Hello {{manager_name}},</p>
                    <p>A property RERA permit has been added and requires your approval.</p>
                    <p>
                        <strong>Property Title:</strong> {{property_title}}<br>
                        <strong>Reference Number:</strong> {{property_id}}<br>
                        <strong>Listing Type:</strong> {{listing_type}}<br>
                        <strong>City:</strong> {{city}}<br>
                        <strong>Community:</strong> {{community}}<br>
                        <strong>Sub Community:</strong> {{sub_community}}<br>
                        <strong>Tower:</strong> {{tower}}<br>
                        <strong>RERA Permit Number:</strong> {{rera_permit_no}}<br>
                        <strong>Added By:</strong> {{added_by}}<br>
                        <strong>Date:</strong> {{added_date}}
                    </p>
                    <p>
                        <a href="{{property_link}}" style="display:inline-block;padding:12px 24px;background-color:#10b981;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            Review & Approve Property
                        </a>
                    </p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{manager_name}}, {{property_title}}, {{property_id}}, {{listing_type}}, {{city}}, {{community}}, {{sub_community}}, {{tower}}, {{rera_permit_no}}, {{added_by}}, {{added_date}}, {{property_link}}, {{company_name}}',
            ],
            [
                'title' => 'Permit Entered Agent',
                'slug' => Constants::EMAIL_SLUGS['property']['permit_entered_agent'],
                'subject' => 'Permit Number Added - {{property_title}} - {{property_id}}',
                'emailContent' => '
                    <p>Hello {{agent_name}},</p>
                    <p>A permit number has been added to your property listing.</p>
                    <p>
                        <strong>Property Title:</strong> {{property_title}}<br>
                        <strong>Reference Number:</strong> {{property_id}}<br>
                        <strong>Permit Number:</strong> {{permit_number}}<br>
                        <strong>Added By:</strong> {{added_by}}<br>
                        <strong>Date:</strong> {{added_date}}
                    </p>
                    <p>Your listing is now in Draft. Please review it and request approval.</p>
                    <p>
                        <a href="{{property_link}}" style="display:inline-block;padding:12px 24px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            View Property
                        </a>
                    </p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{agent_name}}, {{property_title}}, {{property_id}}, {{permit_number}}, {{added_by}}, {{added_date}}, {{property_link}}, {{company_name}}',
            ],
            [
                'title' => 'Listing Approved Agent',
                'slug' => Constants::EMAIL_SLUGS['property']['listing_approved_agent'],
                'subject' => 'Property Approved - {{property_title}} - {{property_id}}',
                'emailContent' => '
                    <p>Hello {{agent_name}},</p>
                    <p>Your property listing has been approved.</p>
                    <p>
                        <strong>Property Title:</strong> {{property_title}}<br>
                        <strong>Reference Number:</strong> {{property_id}}<br>
                        <strong>City:</strong> {{city}}<br>
                        <strong>Community:</strong> {{community}}<br>
                        <strong>Sub Community:</strong> {{sub_community}}<br>
                        <strong>Tower:</strong> {{tower}}<br>
                        <strong>Status:</strong> {{status}}<br>
                        <strong>Reviewed By:</strong> {{reviewed_by}}<br>
                        <strong>Reviewed Date:</strong> {{reviewed_date}}
                    </p>
                    <p>
                        <a href="{{property_link}}" style="display:inline-block;padding:12px 24px;background-color:#10b981;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            View Property
                        </a>
                    </p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{agent_name}}, {{property_title}}, {{property_id}}, {{city}}, {{community}}, {{sub_community}}, {{tower}}, {{status}}, {{reviewed_by}}, {{reviewed_date}}, {{property_link}}, {{company_name}}',
            ],
            [
                'title' => 'Listing Rejected Agent',
                'slug' => Constants::EMAIL_SLUGS['property']['listing_rejected_agent'],
                'subject' => 'Property Rejected - {{property_title}} - {{property_id}}',
                'emailContent' => '
                    <p>Hello {{agent_name}},</p>
                    <p>Your property listing has been rejected.</p>
                    <p>
                        <strong>Property Title:</strong> {{property_title}}<br>
                        <strong>Reference Number:</strong> {{property_id}}<br>
                        <strong>City:</strong> {{city}}<br>
                        <strong>Community:</strong> {{community}}<br>
                        <strong>Sub Community:</strong> {{sub_community}}<br>
                        <strong>Tower:</strong> {{tower}}<br>
                        <strong>Status:</strong> {{status}}<br>
                        <strong>Comment:</strong> {{comment}}<br>
                        <strong>Reviewed By:</strong> {{reviewed_by}}<br>
                        <strong>Reviewed Date:</strong> {{reviewed_date}}
                    </p>
                    <p>
                        <a href="{{property_link}}" style="display:inline-block;padding:12px 24px;background-color:#ef4444;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            View Property
                        </a>
                    </p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{agent_name}}, {{property_title}}, {{property_id}}, {{city}}, {{community}}, {{sub_community}}, {{tower}}, {{status}}, {{comment}}, {{reviewed_by}}, {{reviewed_date}}, {{property_link}}, {{company_name}}',
            ],
            [
                'title' => 'Listing Published Agent',
                'slug' => Constants::EMAIL_SLUGS['property']['listing_published'],
                'subject' => 'Property Published - {{property_title}} - {{property_id}}',
                'emailContent' => '
                    <p>Hello {{agent_name}},</p>
                    <p>Your property listing has been published and is now live.</p>
                    <p>
                        <strong>Property Title:</strong> {{property_title}}<br>
                        <strong>Reference Number:</strong> {{property_id}}<br>
                        <strong>City:</strong> {{city}}<br>
                        <strong>Community:</strong> {{community}}<br>
                        <strong>Sub Community:</strong> {{sub_community}}<br>
                        <strong>Tower:</strong> {{tower}}<br>
                        <strong>Published By:</strong> {{published_by}}<br>
                        <strong>Published Date:</strong> {{published_date}}
                    </p>
                    <p>
                        <a href="{{property_link}}" style="display:inline-block;padding:12px 24px;background-color:#10b981;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            View Property
                        </a>
                    </p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{agent_name}}, {{property_title}}, {{property_id}}, {{city}}, {{community}}, {{sub_community}}, {{tower}}, {{published_by}}, {{published_date}}, {{property_link}}, {{company_name}}',
            ],
            [
                'title' => 'Listing Published Owner',
                'slug' => Constants::EMAIL_SLUGS['property']['listing_published_owner'],
                'subject' => 'Your Property Is Now Live - {{property_title}} - {{property_id}}',
                'emailContent' => '
                    <p>Hello {{owner_name}},</p>
                    <p>We are pleased to inform you that your property listing has been published and is now live.</p>
                    <p>
                        <strong>Property Title:</strong> {{property_title}}<br>
                        <strong>Reference Number:</strong> {{property_id}}<br>
                        <strong>City:</strong> {{city}}<br>
                        <strong>Community:</strong> {{community}}<br>
                        <strong>Sub Community:</strong> {{sub_community}}<br>
                        <strong>Tower:</strong> {{tower}}<br>
                        <strong>Published Date:</strong> {{published_date}}
                    </p>
                    <p>Thank you for choosing us to manage your property.</p>
                    <p>Best regards,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{owner_name}}, {{property_title}}, {{property_id}}, {{city}}, {{community}}, {{sub_community}}, {{tower}}, {{published_date}}, {{company_name}}',
            ],
            [
                'title' => '4PM Daily Enquiry Matching Email',
                'slug' => Constants::EMAIL_SLUGS['enquiry']['daily_matching_4pm'],
                'subject' => 'Daily Enquiry Matching Email – Enquiries',
                'emailContent' => '
                    <p>Hi {{agent_name}},</p>

                    <p>We have automatically matched all your active enquiries with the active listings, we have listed the matched listings for today.</p>

                    {{matching_table}}

                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{agent_name}}, {{matching_table}}, {{company_name}}',
            ],
            [
                'title' => 'Daily Enquiry Matching Report',
                'slug' => Constants::EMAIL_SLUGS['enquiry']['daily_matching'],
                'subject' => 'Daily Enquiry Matching Email - Enquiries',
                'emailContent' => '
                    <p>Hi {{agent_name}},</p>

                    <p>We have automatically matched all your active enquiries with the active listings. Below are the matched listings for today.</p>

                    <table style="width:100%; border-collapse:collapse; margin:20px 0; font-size:14px;">
                        <thead>
                            <tr style="background-color:#00002a; color:#ffffff;">
                                <th style="padding:10px 12px; text-align:left; border:1px solid #e5e7eb;">S.No</th>
                                <th style="padding:10px 12px; text-align:left; border:1px solid #e5e7eb;">Enquiry Ref#</th>
                                <th style="padding:10px 12px; text-align:left; border:1px solid #e5e7eb;">Listing Ref#</th>
                                <th style="padding:10px 12px; text-align:left; border:1px solid #e5e7eb;">Listing Owner</th>
                                <th style="padding:10px 12px; text-align:left; border:1px solid #e5e7eb;">Property Owner</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{matching_table_rows}}
                        </tbody>
                    </table>

                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{agent_name}}, {{matching_table_rows}}, {{company_name}}',
            ],
            [
                'title' => 'Daily Listing Matching Report',
                'slug' => Constants::EMAIL_SLUGS['enquiry']['daily_listing_matching'],
                'subject' => 'Daily Enquiry Matching Email - Listings',
                'emailContent' => '
                    <p>Hi {{agent_name}},</p>

                    <p>We have automatically matched all your active listings with the active enquiries. Below are the matched enquiries for today.</p>

                    {{matching_table}}

                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{agent_name}}, {{matching_table}}, {{company_name}}',
            ],
            [
                'title' => 'Open Game Enquiry Reminder',
                'slug' => Constants::EMAIL_SLUGS['enquiry']['open_game_reminder'],
                'subject' => 'Daily Enquiry Matching Email for Open Game',
                'emailContent' => '
                    <p>Hi {{agent_name}},</p>

                    <p>This is your list of active enquiries that will be moved to <strong>Open Game</strong> within 24 hours if no activity is completed in this time frame.</p>

                    <p>Please click the link below and set a call, task, or event at the earliest to retain the enquiry.</p>

                    <p style="margin:20px 0;">
                        <a href="{{action_link}}" style="display:inline-block; padding:12px 24px; background-color:#2563eb; color:#ffffff; text-decoration:none; border-radius:5px; font-weight:bold;">
                            View Enquiries
                        </a>
                    </p>

                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{agent_name}}, {{action_link}}, {{company_name}}',
            ],
            [
                'title' => 'OnBoarding : Buyers Guide',
                'slug' => Constants::EMAIL_SLUGS['contact']['buyers_guide'],
                'subject' => 'Exclusive Links: Buyers Guide',
                'emailContent' => '
                    <p>Dear {{contact_name}},</p>

                    <p>I hope you\'re well.</p>

                    <p>Thank you for enquiring with Exclusive Links Real Estate Brokers.</p>

                    <p>In this email, we have outlined some of the costs associated with buying a resale property in Dubai. Not all the costs and processes will be applicable to you, especially if you do not require finance options (mortgage) for the purchase or in the event you wish to purchase a primary/off-plan property.</p>

                    <p>Firstly, a little bit about us. Exclusive Links has been specializing in the Dubai real estate market for 17 years. We operate two offices in Dubai, the first, our Dubai Marina Branch located in the prestigious Marina Gate, Dubai Marina Walk and our head office which is in Control Tower, Motor City.</p>

                    <p>Purchasing a property is a very exciting time but it can also be quite stressful. Our aim is to make your purchasing experience as smooth as possible.</p>

                    <p>The off-plan market is in high demand as more selection of product, locations and competitive payment plans are available. The choice is so vast it can be confusing! Our dedicated and service-oriented team will guide you through project by project, step by step.</p>

                    <p>On the initial purchase of an off-plan property, Buyers will pay between 5 &ndash; 10% initial deposit, 4% Dubai Land Registration fees and AED 3,000 Oqood fee. Further payments are to be followed as per the agreed payment plan outlined in the Purchase and Sales Agreement between Buyer and Developer.</p>

                    <p>The secondary/resale market has more costs and a different process to be considered and we have provided a detailed guide of these for your consideration.</p>

                    <hr style="border:1px solid #e5e7eb; margin:24px 0;">

                    <h3 style="color:#00002a; font-size:16px; margin-bottom:16px;">COSTS ASSOCIATED WITH BUYING</h3>

                    <p><strong style="color:#c9a84c;">TRANSFER FEES</strong><br>
                    4% of property price + AED 580/430 if property type is land on title deed<br>
                    Payable to <strong>DUBAI LAND DEPARTMENT</strong><br>
                    Only payable by <strong>MANAGERS CHEQUE / CASH</strong> below AED 10,000/- only</p>

                    <p><strong style="color:#c9a84c;">TRANSFER FEES (Oqood &amp; Pre-Title Deed)</strong><br>
                    4% of property price + AED 40<br>
                    Payable to <strong>DUBAI LAND DEPARTMENT</strong><br>
                    Only payable by <strong>MANAGERS CHEQUE / CASH</strong> below AED 10,000/- only</p>

                    <p><strong style="color:#c9a84c;">AGENCY FEES</strong><br>
                    2% or AED 20,000 + 5% VAT (whichever is higher)<br>
                    AED 4,000 + 5% VAT sales professional fee<br>
                    Payable to <strong>EXCLUSIVE LINKS REAL ESTATE BROKERS</strong><br>
                    Payable by <strong>MANAGERS CHEQUE or BANK TRANSFER</strong></p>

                    <p><strong style="color:#c9a84c;">REGISTRATION TRUSTEE ADMIN FEE</strong><br>
                    AED 4,200 (inclusive of 5% VAT)<br>
                    Payable to <strong>DUBAI LAND DEPARTMENT</strong><br>
                    Payable by <strong>CASH OR CREDIT CARD</strong></p>

                    <p><strong style="color:#c9a84c;">REGISTRATION TRUSTEE ADMIN FEE (Oqood)</strong><br>
                    AED 5,250 (inclusive of 5% VAT)<br>
                    Payable to <strong>DUBAI LAND DEPARTMENT</strong><br>
                    Payable by <strong>CASH OR CREDIT CARD</strong></p>

                    <p><strong style="color:#c9a84c;">MORTGAGE REGISTRATION FEES</strong> <em>(for mortgage buyers only)</em><br>
                    0.25% of property price + AED 290<br>
                    Payable to <strong>DUBAI LAND DEPARTMENT</strong><br>
                    Only payable by <strong>MANAGERS CHEQUE / CASH</strong></p>

                    <p><strong style="color:#c9a84c;">PROPERTY VALUATION FEE</strong> <em>(for mortgage buyers only)</em><br>
                    AED 2,500 to AED 4,000 (depending on which bank/valuer is being instructed)<br>
                    Payable to <strong>APPOINTED VALUER</strong><br>
                    Payable by <strong>BANK TRANSFER</strong></p>

                    <p><strong style="color:#c9a84c;">BANK PROCESSING FEE</strong> <em>(for mortgage buyers only)</em><br>
                    0.5% to 1% (of the loan amount)<br>
                    Payable to <strong>APPOINTED BANK</strong><br>
                    Payable by <strong>BANK TRANSFER</strong></p>

                    <p><strong style="color:#c9a84c;">LIFE INSURANCE</strong> <em>(for mortgage buyers only)</em><br>
                    0.40% to 0.60% (per annum of the mortgage balance for single life)<br>
                    Payable to <strong>APPOINTED BANK</strong><br>
                    Payable by <strong>MONTHLY DIRECT DEBIT</strong></p>

                    <p><strong style="color:#c9a84c;">BUILDING INSURANCE</strong> <em>(for mortgage buyers only)</em><br>
                    0.04% to 0.12% (per annum of the property value)<br>
                    Payable to <strong>APPOINTED BANK</strong><br>
                    Payable by <strong>MONTHLY DIRECT DEBIT</strong></p>

                    <p><strong style="color:#c9a84c;">MORTGAGE BROKER FEES</strong> <em>(for mortgage buyers only)</em><br>
                    AED 5,250 (on average)<br>
                    Payable to the <strong>APPOINTED BROKER</strong></p>

                    <p><strong style="color:#c9a84c;">DEWA SECURITY DEPOSIT</strong><br>
                    AED 2,190 (for apartments) or AED 4,190 (for villas)<br>
                    Payable to <strong>DEWA</strong> (Dubai Electricity and Water Authority)<br>
                    Payable by online <strong>CREDIT CARD</strong></p>

                    <p><em>Please note that the above fees are based on current market standards and practices. The fees relating to purchasing with a mortgage are based on averages and will vary depending on what mortgage product you choose. These may be subject to change.</em></p>

                    <hr style="border:1px solid #e5e7eb; margin:24px 0;">

                    <h3 style="color:#00002a; font-size:16px; margin-bottom:16px;">THE BUYING PROCESS</h3>

                    <p><strong style="color:#c9a84c;">STEP 1 &ndash; BOOK A BUYER CONSULTATION</strong><br>
                    Book an appointment with your Exclusive Links broker to discuss your requirements. Some discussion points will include but not limited to:</p>
                    <ul style="margin:8px 0 16px 20px; color:#333333;">
                        <li>Number of bedrooms</li>
                        <li>Communities of interest</li>
                        <li>Villa or Apartment</li>
                        <li>Do you need a maid\'s room</li>
                        <li>Are you buying with a mortgage</li>
                        <li>What\'s your target date of occupancy</li>
                        <li>What type of view are you looking for</li>
                        <li>Budget range</li>
                    </ul>

                    <p><strong style="color:#c9a84c;">STEP 2 &ndash; PROPERTY SEARCH</strong><br>
                    Once your broker has a good understanding of your requirements, we will get to work and source properties for your consideration. These properties can be direct units listed with Exclusive Links or listed by third-party agencies. Either way, we will find it. You can forward any links to properties you find online to your broker, and they will call the brokers on your behalf and schedule viewings accordingly. This will save you time and not cost you any more in fees.</p>

                    <p><strong style="color:#c9a84c;">STEP 3 &ndash; VIEWING APPOINTMENTS</strong><br>
                    The next step is to schedule viewing appointments. Please note that it is highly likely that several units may have sitting tenants and the viewing timings are subject to their availability. We typically aim to schedule 2&ndash;3 properties for you to view in one appointment, unless we have identified one very specific unit type that you are focused on.</p>

                    <p><strong style="color:#c9a84c;">STEP 4 &ndash; SUBMITTING AN OFFER</strong><br>
                    Once you have identified a suitable property, the next step is to make an official offer to the seller. Your broker will prepare an offer letter detailing the property particulars and a breakdown of the fees associated with that property. It will also detail whether you are offering to purchase the property in cash or through bank finance and any other special requirements that you may have, such as:</p>
                    <ul style="margin:8px 0 16px 20px; color:#333333;">
                        <li>An extended transfer deadline based on travel plans or finance-related aspects</li>
                        <li>Confirmation of tenant vacancy by a certain date for occupancy</li>
                        <li>Furniture or appliances to be included within the offered price</li>
                    </ul>

                    <p><strong style="color:#c9a84c;">STEP 5 &ndash; THE AGREEMENT OF SALE AND PROGRESSION</strong><br>
                    Once a sale is agreed, our sales progression team will prepare and manage the sale contracts and take the process to the transfer of ownership. We will also be custodian of the 10% security cheque that a buyer provides upon reserving the unit.</p>

                    <p><strong style="color:#c9a84c;">STEP 6 &ndash; POWER OF ATTORNEY</strong><br>
                    A sale transfer can take up to 30&ndash;90 days to complete, depending on the circumstances of the deal. Your presence is required on a few occasions during this process. If your time is limited and you are a frequent traveller or live outside of Dubai, then you can take advantage of Power of Attorney services that we offer. We can assist with the preparation of the POA documents and attend all the appointments on your behalf in order to complete the sale. POA services cost a total of AED 5,250 per individual per property. Please note a POA is valid for 2 years and can be attested again by a simple stamp at the notary.</p>

                    <p><strong style="color:#c9a84c;">STEP 7 &ndash; PROPERTY VALUATION AND FINAL MORTGAGE OFFER LETTER (FOL)</strong><br>
                    If you are buying the property with a mortgage, then your bank will appoint an independent home valuation from one of their approved panel members. You will be requested to pay the cost of the valuation prior to the appointment. The result of the valuation can take up to 3 working days. Your bank will lend on the valuation amount or the agreed sale price if the sale price is lower than the valuation amount. If the sale price is higher than the valuation, then you would have to bridge the gap between the valuation price and the sale price to go ahead with the sale.</p>

                    <p><strong style="color:#c9a84c;">STEP 8 &ndash; LIABILITY LETTER &amp; NOC</strong><br>
                    If the property is currently mortgaged, then it is the responsibility of the seller to request a liability letter from their bank so your bank (if applicable) can settle the outstanding amount. It can take up to 3 weeks typically to get a liability from the date of request and then the mortgage can be cleared. The seller\'s bank will take around 2&ndash;3 weeks to produce the original title deed and clearance documents. Once the original title deed and clearance documents are ready, the seller can apply for a No Objection Certificate (NOC) from the developer. The NOC is to confirm that there are no liabilities on the property and that the seller has paid their annual service charge fees up to date. The NOC can take up to 7 working days to be ready, depending on the developer.</p>

                    <p><strong style="color:#c9a84c;">STEP 9 &ndash; SCHEDULING A TRANSFER APPOINTMENT</strong><br>
                    Once all processes above are complete, we can then schedule an appointment at one of the Dubai Land Department Registration Trustee Offices to complete the transaction. Both parties have to be present or their nominated POAs. The process can take up to 3&ndash;4 hours. This is where the title deed will be printed in the buyer\'s name and the sale proceed cheque will be issued to the seller.</p>

                    <p><strong>Rent Reimbursement:</strong> If there is a sitting tenant during the time of transfer, rent shall be reimbursed to the buyer on transfer day on a pro-rata basis. The tenant\'s refundable security should also be handed over along with the original tenancy contract and any receipts/warranties/letters.</p>

                    <p><strong>Service Fee Reimbursement:</strong> The buyer shall reimburse the annual service fees from the date of their ownership for any advance fees already paid by the seller, on a pro-rata basis.</p>

                    <p><strong>Collection of Security Deposits from Utility Providers:</strong> Once the transfer is complete, it is the responsibility of the buyer to register with DEWA and any other chiller provider (if applicable) as the new owner of the property. This is required in order for the seller to obtain their security deposits back.</p>

                    <p><strong style="color:#c9a84c;">STEP 10 &ndash; PROPERTY MANAGEMENT &amp; IFX (FOREIGN EXCHANGE)</strong><br>
                    If you are sending any funds from a different currency into AED then speak to our partner Sarah at IFX Payments. She can talk you through how best to proceed and make you savings of up to 2.5% on the transferred amount compared to the standard exchange rate your bank will offer you. If you are planning on renting out your property, then Exclusive Links Property Management Services can be the best way to maximize your investment &ndash; enquire for further details.</p>

                    <hr style="border:1px solid #e5e7eb; margin:24px 0;">

                    <p>We hope the above has helped and we look forward to working with you on your property search.</p>

                    <p>Please let us know if you have any immediate questions.</p>

                    <p>On behalf of the Exclusive Links Team, we would like to thank you for your business.</p>

                    <p>Please do not hesitate to contact me for any additional support you need, and I will be in touch again soon.</p>

                    <p>Best regards,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{contact_name}}, {{company_name}}',
            ],
            [
                'title' => 'IFX Payment: Currency Exchange Partner',
                'slug' => Constants::EMAIL_SLUGS['property']['ifx_payment_partner'],
                'subject' => 'IFX Payment: Our Preferred Partner for Currency Exchange',
                'emailContent' => '
                    <p>Dear {{contact_name}},</p>

                    <p>Exclusive Links has teamed up with International Foreign Exchange to offer professional help with buying foreign currency. IFX Payments specializes in offering preferential rates when making international foreign currency transfers, helping you to make savings on the transferred amount compared to the standard exchange rate your bank will offer you.</p>

                    <p>IFX Payments is a foreign exchange and payments remittance provider based in the United Kingdom, providing competitive foreign exchange rates and a higher quality, more responsive service. Authorised by the Dubai Financial Services Authority as a Representative Office (F001814) and designed to market these services to you.</p>

                    <p>As a client of IFX Payments, you will have direct access to the dealing team in the United Kingdom who are responsible for delivering trades; and you will be appointed a designated Dubai Account Manager and point of contact on all other matters.</p>

                    <p>The key benefits to using IFX Payments for your foreign exchange needs include:</p>
                    <ul style="margin:8px 0 16px 20px; color:#333333;">
                        <li>Dealing directly with our UK-based professional dealers</li>
                        <li>Same day payments for most currencies</li>
                        <li>Better FX rates than the major banks</li>
                        <li>IFX is authorised and regulated by the UK Financial Conduct Authority under the Electronic Money Regulations 2011</li>
                    </ul>

                    <p>Please let me know if you would like us to connect you to our IFX representative.</p>

                    <p>Best regards,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{contact_name}}, {{company_name}}',
            ],
            [
                'title' => 'OnBoarding : Landlord Guide',
                'slug' => Constants::EMAIL_SLUGS['contact']['landlord_guide'],
                'subject' => 'Exclusive Links: Landlord Guide',
                'emailContent' => '
                    <p>Dear {{contact_name}},</p>

                    <p>I hope you\'re well.</p>

                    <p>Thanks for enquiring with Exclusive Links Real Estate Brokers.</p>

                    <p>In this email, we have outlined some of the process and procedures associated with renting out your property.</p>

                    <p>Firstly, a little bit about us. Exclusive Links has been specializing in the Dubai Real Estate market for 17 years. We operate two offices in Dubai, the first, our Dubai Marina Branch located in the prestigious Marina Gate, Dubai Marina Walk and our head office which is in Control Tower, Motor City.</p>

                    <p>At Exclusive Links we aim to obtain the best rents possible for our landlords to maximize your investment. We are also conscious of making sure we find the right profile of tenant for you.</p>

                    <p>We can also provide introductions to preferred partners for additional services that you may require along the way.</p>

                    <hr style="border:1px solid #e5e7eb; margin:24px 0;">

                    <h3 style="color:#00002a; font-size:16px; margin-bottom:16px;">THE RENTAL PROCESS</h3>

                    <p><strong style="color:#c9a84c;">STEP 1 &ndash; BOOK A CONSULTATION WITH YOUR BROKER</strong><br>
                    Meet with one of our experts to discuss your property. It is also important that your agent visits the property so they can familiarize themselves prior to scheduling viewings. Some considerations for you prior to your agent showing clients:</p>
                    <ul style="margin:8px 0 16px 20px; color:#333333;">
                        <li>If the property currently has a sitting tenant, please inform your tenant that your agent will be in touch to schedule viewings at a suitable time. It\'s important that the tenant makes sure the property is in a suitable condition prior to viewing.</li>
                        <li>If the property is vacant, we advise you to activate DEWA to have the A/C working to avoid any damp/condensation issues which can lead to very expensive maintenance. It also allows your broker to carry out evening viewings.</li>
                        <li>If the property is vacant, we advise you to instruct a maintenance company to go through any snag/maintenance issues that will have to be rectified prior to the new tenant moving in. This also helps make the property more presentable for when your agent takes clients for viewings.</li>
                        <li>Discuss any requirements you have with your broker about the profile of tenant you ideally want to rent your property.</li>
                        <li>Discuss the marketing price of your property with your broker.</li>
                        <li>Arrange for your broker to gain easy access to the property by either registering a key with us or instructing the broker to meet you or a representative/POA who will arrange access.</li>
                        <li>Sometimes building management will require you to give a No Objection Certificate (NOC) or send an NOC email to building management informing them that your broker will be carrying out viewings on the property.</li>
                    </ul>

                    <p><strong style="color:#c9a84c;">STEP 2 &ndash; RECEIVING AN OFFER</strong><br>
                    When your broker receives an offer, they will include the following information:</p>
                    <ul style="margin:8px 0 16px 20px; color:#333333;">
                        <li>Rent price</li>
                        <li>Number of cheques</li>
                        <li>Move date</li>
                        <li>Highlight any maintenance issues to be rectified prior to the move-in date</li>
                        <li>Disclose the place of employment and position held by the tenant</li>
                        <li>Disclose the occupants and/or pets that will reside in the property</li>
                        <li>Disclose the nationality and the length of time the tenant has been residing in Dubai</li>
                        <li>Confirm the tenant has a valid Passport/Visa and EID</li>
                    </ul>

                    <p>In most cases tenants will aim to offer below the advertising price. Your broker will discuss the initial offer and negotiate on your behalf to obtain a deal that you are happy with. It\'s important to respond to your broker in a prompt manner to avoid losing the tenant to another property. In most cases the tenants are viewing multiple properties and restricted by tight timelines.</p>

                    <p><strong style="color:#c9a84c;">STEP 3 &ndash; TENANCY CONTRACT</strong><br>
                    Once an offer is verbally agreed by you and the tenant, your broker will prepare the tenancy contract for review. Once happy, the tenant will sign first and hand over the rent and security deposit cheque to your broker. You will then counter-sign and take the cheques. The first cheque will be dated at least 3 working days prior to the tenancy start date for the payment to clear before we hand the keys over to the tenant.</p>

                    <p><strong style="color:#c9a84c;">STEP 4 &ndash; PROPERTY HANDOVER</strong><br>
                    Your broker and tenant will highlight any maintenance issues that have to be repaired prior to the move-in date. It is also the landlord\'s responsibility to make sure the previous Ejari is cancelled, and the previous tenant paid and received their final DEWA bill and chiller bill if applicable. It is also required for the property\'s annual service charges to be paid up to date for building management to issue the tenant with a move-in permit.</p>

                    <p>Once the tenant takes occupancy, the tenant will communicate with you directly for any ongoing issues or requests. Unless you choose to take on our Property Management service, in which case we handle all of that for you.</p>

                    <hr style="border:1px solid #e5e7eb; margin:24px 0;">

                    <p>On behalf of the Exclusive Links Team, we would like to thank you for your business.</p>

                    <p>Please do not hesitate to contact me for any additional support you need, and I will be in touch again soon.</p>

                    <p>Kind regards,<br><strong>{{agent_name}}</strong></p>
                ',
                'macro' => '{{contact_name}}, {{agent_name}}, {{company_name}}',
            ],
            [
                'title' => 'Mortgage Solutions: Preferred Partner',
                'slug' => Constants::EMAIL_SLUGS['property']['mortgage_solutions'],
                'subject' => 'Mortgage Solutions: Our Preferred Partner for Mortgages',
                'emailContent' => '
                    <p>Dear {{contact_name}},</p>

                    <p>Exclusive Links Real Estate can put you in touch with our preferred partner for mortgages, Mortgage Solutions.</p>

                    <p>Mortgage Solutions specialises in the best mortgage products in Dubai. They work with all the banks and help you choose the best rate and product on the market.</p>

                    <h3 style="color:#00002a; font-size:16px; margin-bottom:16px;">Why use Mortgage Solutions?</h3>

                    <p><strong style="color:#c9a84c;">To Save You Time</strong><br>
                    They will compare all available mortgages and recommend the best one for you. This means that you don\'t have to negotiate with 20+ banks yourself.</p>

                    <p><strong style="color:#c9a84c;">To Save You Money</strong><br>
                    They have relationships with all the top lenders in the UAE. This gives them access to the best mortgage rates as well as exclusive discounts on fees and interest rates.</p>

                    <p><strong style="color:#c9a84c;">A VIP Service</strong><br>
                    Mortgage Solutions manages the mortgage process from start to finish, dealing with all potential hurdles to ensure you\'re able to buy your dream property. They work for you, not banks.</p>

                    <p>Please let us know if you would like us to connect you with a Mortgage Broker for a free consultation.</p>

                    <p>Best regards,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{contact_name}}, {{company_name}}',
            ],
            [
                'title' => 'OnBoarding : Sellers Guide',
                'slug' => Constants::EMAIL_SLUGS['contact']['sellers_guide'],
                'subject' => 'Exclusive Links: Sellers Guide',
                'emailContent' => '
                    <p>Dear {{contact_name}},</p>

                    <p>I hope you\'re well.</p>

                    <p>Thank you for enquiring with Exclusive Links Real Estate Brokers to sell your property.</p>

                    <p>In this email, we have outlined some of the costs and processes associated with selling a property in Dubai. Not all of the costs and processes will be applicable to you, especially if you do not have a mortgage on the property.</p>

                    <p>Firstly, a little bit about us. Exclusive Links has been specializing in the Dubai Real Estate market for 18 years. We operate two offices in Dubai, the first, our Dubai Marina Branch located in the prestigious Marina Gate, Dubai Marina Walk and our head office in Control Tower, Motor City.</p>

                    <p>At Exclusive Links we aim to obtain the best sale price for our sellers to maximise your return from your investment. Our aim is to make your selling experience as smooth and hassle-free as possible.</p>

                    <hr style="border:1px solid #e5e7eb; margin:24px 0;">

                    <h3 style="color:#00002a; font-size:16px; margin-bottom:16px;">THE SELLING PROCESS</h3>

                    <p><strong style="color:#c9a84c;">STEP 1 &ndash; VALUING YOUR PROPERTY &amp; APPROVING THE MARKETING PRICE</strong><br>
                    Once you have established that you wish to sell your property, your broker will carry out a Comparable Market Analysis (CMA) of your property to help determine the best possible price it can be sold for. Once you have approved the sale value, your broker will prepare the Dubai Land Department listing Form A which provides your permission for Exclusive Links to market and advertise your property. This is a government requirement before we can commence marketing of your property.</p>

                    <p><strong style="color:#c9a84c;">STEP 2 &ndash; PREPARING THE PROPERTY</strong><br>
                    It is important that the property is in a good or reasonable condition when clients come to view.</p>

                    <p><strong>If the property is vacant:</strong></p>
                    <ul style="margin:8px 0 16px 20px; color:#333333;">
                        <li>Connect DEWA and have the A/C on auto so it is cool for clients during the viewing. This also helps with preventing condensation, humidity within the property and evening viewings.</li>
                        <li>Have the property cleaned and freshly painted in a neutral colour.</li>
                        <li>Remove any old items of furniture that have a lot of wear and tear or do not fit within the layout or style of the unit.</li>
                        <li>Clean and service the A/C units.</li>
                        <li>Have a maintenance company come and snag the property of any issues that a buyer may bring up during the viewing.</li>
                    </ul>

                    <p><strong>If the property is occupied by yourself or a tenant:</strong></p>
                    <ul style="margin:8px 0 16px 20px; color:#333333;">
                        <li>Try and find suitable times on a weekly basis that work for you or the tenant to arrange viewings with profiled potential purchasers.</li>
                        <li>Try and declutter as much as possible &amp; remove/hide personal effects.</li>
                        <li>Keep the living room and bedroom curtains open during the viewing &amp; lights on.</li>
                        <li>Make sure all bedrooms are accessible during the viewing.</li>
                        <li>Avoid cooking whilst viewings are in progress.</li>
                        <li>Keep dogs or pets under control whilst viewings are in progress.</li>
                    </ul>

                    <p><strong style="color:#c9a84c;">STEP 3 &ndash; FINDING A BUYER</strong><br>
                    It can be difficult to predict how long it will take to secure a buyer. Here are some reasons why:</p>
                    <ul style="margin:8px 0 16px 20px; color:#333333;">
                        <li>Not enough viewings being carried out on the property. This can typically be attributed to the advertising price or if there are any stand-out issues with the unit such as it facing construction.</li>
                        <li>It can sometimes take a few attempts to secure a viewing appointment with a buyer due to travel/work commitments. This can delay the viewing appointments.</li>
                        <li>Some clients will be purchasing with a mortgage, which can sometimes lead to delays in signing the final sale contract or possibly a withdrawal of the offer if the client is not able to obtain the mortgage.</li>
                    </ul>
                    <p>Please be prepared for some clients to submit an offer but simultaneously still be viewing other units. It\'s in our interest to promptly reply to an offer to avoid such cases. When a buyer is ready to submit an offer, we will try to bring them in to the office to write out the deposit cheque and commit to the Sale Contract in real time. Be rest assured that we do everything we can to qualify the offer prior to submitting it to you. Be prepared for offers being submitted below your asking price. We will always submit each and every offer no matter what the initial bid is.</p>

                    <p><strong style="color:#c9a84c;">STEP 4 &ndash; THE CONTRACT &amp; SALES PROGRESSION</strong><br>
                    Once a sale is agreed, we will appoint a Sales Progressor to prepare and manage the sale contracts. They will also be custodian of the 10% security cheque that a buyer provides upon reserving the unit.</p>
                    <p>Sales Progression can typically cost within the range of AED 8,000 &ndash; AED 12,000 depending on the make-up of the deal. The variance is usually due to whether the property and/or buyer is purchasing with a mortgage and/or if the buyer and/or seller is buying or selling the property in the name of a company. This is typically split 50-50% with the buyer, however, not in all cases.</p>
                    <p><em>Please note that Sales Progression is not legally mandatory in Dubai. The majority of reputable companies include this service as a company policy to safeguard the transaction and benefit the clients.</em></p>

                    <p><strong style="color:#c9a84c;">STEP 5 &ndash; POWER OF ATTORNEY (POA)</strong><br>
                    A sale transfer can take up to 30&ndash;90 days to complete, depending on the circumstances of the deal. Your presence is required on a few occasions during this process. If your time is limited and you are a frequent traveller or live outside of Dubai, then you can take advantage of Power of Attorney services that Sales Progression provide. They will assist with the preparation of the POA documents and attend all the appointments on your behalf to complete the sale. POA services cost in the range of AED 5,000 + 5% VAT for one individual and one property, depending on the exact make-up of the deal.</p>
                    <p>Alternatively, you can nominate a friend or family member who is based in Dubai to be your POA. This would save you the fees of appointing a third-party company. Please note a POA is valid for 5 years and can be attested again by a simple stamp at the notary.</p>

                    <p><strong style="color:#c9a84c;">STEP 6 &ndash; BLOCKING</strong><br>
                    If the property has a mortgage registered against it, then it is in the best interest of the parties to \'Block\' the unit at Dubai Land Department before the buyer settles the outstanding liability. The cost is AED 1,545. It is usually the responsibility of the seller unless otherwise agreed between the parties at the time of making the offer.</p>

                    <p><strong style="color:#c9a84c;">STEP 7 &ndash; NO OBJECTION CERTIFICATE (NOC)</strong><br>
                    It is the responsibility of the seller to pay for and obtain a No Objection Certificate (NOC) from the developer. It may also be required to obtain a clearance certificate from DEWA and any associated chiller provider. You will typically have to pay the current quarter\'s service charge in advance and some developers request service charges to be settled 6 months in advance before they will release the NOC. The cost of the NOC can vary from AED 500 to AED 5,000 depending on the developer. Most developers sit between AED 500 to AED 2,000 typically.</p>

                    <p><strong style="color:#c9a84c;">STEP 8 &ndash; THE TRANSFER</strong><br>
                    Once all processes above are complete, we can then schedule an appointment at a Dubai Land Department office (trustee office) to complete the transaction. Both parties have to be present or their nominated POAs. The process can take up to 3&ndash;4 hours. This is where the title deed will be printed in the buyer\'s name and the sale proceed cheque will be issued to the seller.</p>

                    <p><strong>Seller Agency Fees:</strong> 2% or AED 20,000 (whichever is higher).</p>

                    <p><strong>Rent Reimbursement:</strong> If there is a sitting tenant during the time of transfer, rent shall be reimbursed to the buyer on transfer day on a pro-rata basis. The tenant\'s refundable security deposit should also be handed over along with the original tenancy contract and any receipts/warranties/letters.</p>

                    <p><strong>Service Fee Reimbursement:</strong> The buyer shall reimburse the annual service fees that you paid in advance and at the time of the NOC on a pro-rata basis from the date of transfer.</p>

                    <p><strong style="color:#c9a84c;">STEP 9 &ndash; UTILITIES</strong><br>
                    Once the transfer is complete, it is the responsibility of the buyer to register themselves with DEWA as the new owner of the property. Once the buyer has completed the registration process, you can then apply for your final bill and receive any remaining balance and the security deposit back.</p>

                    <p><strong style="color:#c9a84c;">STEP 10 &ndash; IFX (FOREIGN EXCHANGE)</strong><br>
                    If you are sending any of the sale proceeds to a currency other than AED, with the strong USD/AED it is a great time to take advantage of buying other currencies. Our partner Sarah at IFX Payments can talk you through how best to proceed and make you savings of up to 2.5% on the transferred amount compared to the standard exchange rate your bank will offer you. I can do an email introduction for you to compare the rates they offer.</p>

                    <hr style="border:1px solid #e5e7eb; margin:24px 0;">

                    <p>On behalf of the Exclusive Links Team, we would like to thank you for your business.</p>

                    <p>Please do not hesitate to contact me for any additional support you need, and I will be in touch again soon.</p>

                    <p>Best regards,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{contact_name}}, {{company_name}}',
            ],
            [
                'title' => 'OnBoarding : Tenants Guide',
                'slug' => Constants::EMAIL_SLUGS['contact']['tenants_guide'],
                'subject' => 'Exclusive Links: Tenant\'s Guide',
                'emailContent' => '
                    <p>Dear {{contact_name}},</p>

                    <p>I hope you\'re well.</p>

                    <p>Thank you for enquiring with Exclusive Links Real Estate Brokers.</p>

                    <p>In this email, we have outlined some of the costs associated with renting a property in Dubai.</p>

                    <p>Firstly, a little bit about us. Exclusive Links has been specializing in the Dubai Real Estate market for 17 years. We operate two offices in Dubai, the first, our Dubai Marina Branch located in the prestigious Marina Gate, Dubai Marina Walk and our head office which is in Control Tower, Motor City.</p>

                    <p>Renting a new property is a very exciting time but it can also be stressful. Our aim is to make your experience as smooth as possible.</p>

                    <p>At Exclusive Links we can also provide introductions to preferred partners for additional services that you may require along the way, for example: removal companies, maintenance men and interior decoration including curtains.</p>

                    <hr style="border:1px solid #e5e7eb; margin:24px 0;">

                    <h3 style="color:#00002a; font-size:16px; margin-bottom:16px;">COSTS ASSOCIATED WITH RENTING A PROPERTY</h3>

                    <ul style="margin:8px 0 16px 20px; color:#333333;">
                        <li>5% Refundable Security Deposit (10% if the property is fully furnished)</li>
                        <li>5% or AED 5,000 + VAT Agency Fees (AED 3,000 if the rent is below AED 60,000 P/A)</li>
                        <li>DEWA refundable security deposit AED 2,190 (for apartments) or AED 4,190 (for villas)</li>
                        <li>AC deposit may be required and ranges from AED 1,000 &ndash; AED 2,000 depending on the supplier</li>
                        <li>Ejari Registration Fee AED 230</li>
                    </ul>

                    <p><em>Please note that the above fees are based on current market standards and practices. These may be subject to change.</em></p>

                    <hr style="border:1px solid #e5e7eb; margin:24px 0;">

                    <h3 style="color:#00002a; font-size:16px; margin-bottom:16px;">THE RENTAL PROCESS</h3>

                    <p><strong style="color:#c9a84c;">STEP 1 &ndash; BOOK A CONSULTATION WITH YOUR BROKER</strong><br>
                    Meet with one of our experts to determine your property requirements. Property search details include:</p>
                    <ul style="margin:8px 0 16px 20px; color:#333333;">
                        <li>Area of preference</li>
                        <li>Budget range</li>
                        <li>Number of cheques you require</li>
                        <li>Move date</li>
                        <li>Number of bedrooms</li>
                        <li>Type of view</li>
                        <li>Floor level in buildings</li>
                        <li>Furnished or unfurnished</li>
                    </ul>

                    <p><strong style="color:#c9a84c;">STEP 2 &ndash; CHOOSE WHICH PROPERTIES TO VIEW</strong><br>
                    Your broker will send you properties that match your requirement. Identify which ones you would like to visit and your broker will schedule the viewings accordingly. If you are searching for yourself on portals like Property Finder &amp; Bayut, send the links of properties you like to your broker, and they can connect with the listing agent on your behalf and schedule the viewings. There are no additional costs for this, you still pay the same fees.</p>

                    <p><strong style="color:#c9a84c;">STEP 3 &ndash; MAKE AN OFFER</strong><br>
                    When making an offer you should include the following information:</p>
                    <ul style="margin:8px 0 16px 20px; color:#333333;">
                        <li>Rent price</li>
                        <li>Number of cheques</li>
                        <li>Move date</li>
                        <li>Highlight any maintenance issues you would like the landlord to rectify before the move</li>
                        <li>Copy of your passport/visa and EID</li>
                        <li>Disclose your place of employment and position held</li>
                        <li>Disclose the occupants and/or pets that will reside in the property</li>
                    </ul>

                    <p><strong style="color:#c9a84c;">STEP 4 &ndash; TENANCY CONTRACT</strong><br>
                    Your broker will prepare the tenancy contract for review. Once happy, you will sign the tenancy and hand over the deposit and rent cheques and your broker will get the tenancy counter-signed by the landlord.</p>

                    <p><strong style="color:#c9a84c;">STEP 5 &ndash; PROPERTY HANDOVER</strong><br>
                    The first rent cheque should be dated at least 3 working days prior to the start date of the lease, and it should show as clear funds in the landlord\'s account before key handover. You will also have to register with DEWA and the chiller provider (if applicable) prior to move-in. Your building management will also require a copy of the registered Ejari and a move-in application form for them to provide you with a move-in permit. Any ongoing requests or maintenance issues in the property should be sent over to the landlord directly unless the property is managed by Exclusive Links, in which case you will be introduced to a Property Supervisor to report the same.</p>

                    <hr style="border:1px solid #e5e7eb; margin:24px 0;">

                    <p>On behalf of the Exclusive Links Team, we would like to thank you for your business.</p>

                    <p>Please do not hesitate to contact me for any additional support you need, and I will be in touch again soon.</p>

                    <p>Best regards,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{contact_name}}, {{company_name}}',
            ],
            [
                'title' => 'Task Reminder',
                'slug' => Constants::EMAIL_SLUGS['task']['task_reminder'],
                'subject' => 'Task Reminder',
                'emailContent' => '
                    <p>Hello {{name}},</p>
                    <p>This is a friendly reminder for the following task:</p>
                    <p>
                        <strong>Task:</strong> {{task_title}}<br>
                        <strong>Type:</strong> {{type}}<br>
                        <strong>Reminder Date:</strong> {{reminderDate}}<br>
                        <strong>Reminder Time:</strong> {{reminderTime}}
                    </p>
                    <p><strong>Comments:</strong> {{comments}}</p>
                    <p>Please make sure to complete this task on time.</p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{name}}, {{task_title}}, {{type}}, {{reminderDate}}, {{reminderTime}}, {{comments}}, {{company_name}}',
            ],
            [
                'title' => 'Task Assigned Notification',
                'slug' => Constants::EMAIL_SLUGS['task']['task_assigned'],
                'subject' => 'New Task Assigned: {{task_title}}',
                'emailContent' => '
                    <p>Hello {{name}},</p>
                    <p>A new task has been assigned to you by <strong>{{assigned_by}}</strong>.</p>
                    <p>
                        <strong>Task:</strong> {{task_title}}<br>
                        <strong>Type:</strong> {{type}}<br>
                        <strong>Priority:</strong> {{priority}}<br>
                        <strong>Due Date:</strong> {{due_date}}
                    </p>
                    <p><strong>Comments:</strong> {{comments}}</p>
                    <p>Please review and take action accordingly.</p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{name}}, {{assigned_by}}, {{task_title}}, {{type}}, {{priority}}, {{due_date}}, {{comments}}, {{company_name}}',
            ],
            [
                'title' => 'Send Multiple Property Details to Contact',
                'slug' => Constants::EMAIL_SLUGS['property']['sent_multiple_property_details_contact'],
                'subject' => 'Property Details from {{companyName}}',
                'emailContent' => '
                    <p>Dear {{fullName}},</p>
                    <p>We are pleased to share the following property details from {{companyName}} for your review.</p>
                    {{propertyList}}
                    <hr style="border: 1px solid #e5e7eb; margin: 20px 0;">
                    <p>If you have any questions, please contact us at {{companyEmail}} or call {{companyPhone}}.</p>
                    <p>Best regards,<br><strong>{{companyName}}</strong></p>
                ',
                'macro' => '{{fullName}}, {{companyName}}, {{propertyList}}, {{companyEmail}}, {{companyPhone}}',
            ],
            [
                'title' => 'Listing Status Change Notification',
                'slug' => Constants::EMAIL_SLUGS['property']['listing_status_change'],
                'subject' => 'Listing Status Changed - {{property_title}} - {{property_id}}',
                'emailContent' => '
                    <p>Hello {{recipient_name}},</p>
                    <p>Listing status has been changed.</p>
                    <p>
                        <strong>Property Title:</strong> {{property_title}}<br>
                        <strong>Reference Number:</strong> {{property_id}}<br>
                        <strong>New Status:</strong> {{status}}<br>
                        <strong>Changed By:</strong> {{changed_by}}<br>
                        <strong>Date:</strong> {{changed_date}}
                    </p>
                    <p>
                        <a href="{{property_link}}" style="display:inline-block;padding:12px 24px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            View Property
                        </a>
                    </p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{recipient_name}}, {{property_title}}, {{property_id}}, {{status}}, {{changed_by}}, {{changed_date}}, {{property_link}}, {{company_name}}',
            ],
            [
                'title' => 'Published Listing Key Data Changed',
                'slug' => Constants::EMAIL_SLUGS['property']['published_listing_key_data_changed'],
                'subject' => 'Published Listing Updated - {{field_changed}} - {{listing_reference}}',
                'emailContent' => '
                    <p>Hello {{recipient_name}},</p>
                    <p>A key field was changed on a published listing.</p>
                    <p>
                        <strong>Listing Reference:</strong> {{listing_reference}}<br>
                        <strong>Property Title:</strong> {{property_title}}<br>
                        <strong>Changed By:</strong> {{changed_by}}<br>
                        <strong>Field Changed:</strong> {{field_changed}}<br>
                        <strong>Old Value:</strong> {{old_value}}<br>
                        <strong>New Value:</strong> {{new_value}}<br>
                        <strong>Date:</strong> {{changed_date}}
                    </p>
                    <p>
                        <a href="{{property_link}}" style="display:inline-block;padding:12px 24px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            View Listing
                        </a>
                    </p>
                    <p>Please review this change to protect live portal and website data.</p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{recipient_name}}, {{listing_reference}}, {{property_title}}, {{changed_by}}, {{field_changed}}, {{old_value}}, {{new_value}}, {{changed_date}}, {{property_link}}, {{company_name}}',
            ],
            [
                'title' => 'Offer Created - Agent Notification',
                'slug' => Constants::EMAIL_SLUGS['offer']['offer_created_agent'],
                'subject' => 'New Offer Assigned - {{offer_name}} - {{offer_id}}',
                'emailContent' => '
                    <p>Hello {{recipient_name}},</p>
                    <p>A new offer has been created and assigned to you.</p>
                    <p>
                        <strong>Offer:</strong> {{offer_name}}<br>
                        <strong>Reference:</strong> {{offer_id}}<br>
                        <strong>Property:</strong> {{property_title}}<br>
                        <strong>Offered Amount:</strong> {{offered_amount}}<br>
                        <strong>Status:</strong> {{status}}<br>
                        <strong>Created By:</strong> {{created_by}}<br>
                        <strong>Date:</strong> {{created_date}}
                    </p>
                    <p>
                        <a href="{{offer_link}}" style="display:inline-block;padding:12px 24px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            View Offer
                        </a>
                    </p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{recipient_name}}, {{offer_name}}, {{offer_id}}, {{property_title}}, {{offered_amount}}, {{status}}, {{created_by}}, {{created_date}}, {{offer_link}}, {{company_name}}',
            ],
            [
                'title' => 'Offer Status Change Notification',
                'slug' => Constants::EMAIL_SLUGS['offer']['offer_status_change'],
                'subject' => 'Offer Status Changed - {{offer_name}} - {{offer_id}}',
                'emailContent' => '
                    <p>Hello {{recipient_name}},</p>
                    <p>An offer status has been updated.</p>
                    <p>
                        <strong>Offer:</strong> {{offer_name}}<br>
                        <strong>Reference:</strong> {{offer_id}}<br>
                        <strong>Property:</strong> {{property_title}}<br>
                        <strong>New Status:</strong> {{status}}<br>
                        <strong>Changed By:</strong> {{changed_by}}<br>
                        <strong>Date:</strong> {{changed_date}}
                    </p>
                    <p>
                        <a href="{{offer_link}}" style="display:inline-block;padding:12px 24px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            View Offer
                        </a>
                    </p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{recipient_name}}, {{offer_name}}, {{offer_id}}, {{property_title}}, {{status}}, {{changed_by}}, {{changed_date}}, {{offer_link}}, {{company_name}}',
            ],
            [
                'title' => 'Closing Created - Agent Notification',
                'slug' => Constants::EMAIL_SLUGS['closing']['closing_created_agent'],
                'subject' => 'Closing Created - {{property_ref}}',
                'emailContent' => '
                    <p>Congratulations {{recipient_name}}! Your closing for {{property_ref}} has been completed. Great work from the {{company_name}}.</p>
                    <p>
                        <strong>Closing Reference:</strong> {{closing_id}}<br>
                        <strong>Property:</strong> {{property_title}}<br>
                        <strong>Client(s):</strong> {{client_names}}<br>
                        <strong>Agent:</strong> {{recipient_name}}<br>
                        <strong>Deal Value:</strong> {{deal_value}}<br>
                        <strong>Expected Closing Date:</strong> {{expected_closing_date}}
                    </p>
                    <p>You can download your SOLD/RENTED social media materials here:<br>
                        <a href="{{closing_link}}" style="display:inline-block;padding:12px 24px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            View Closing
                        </a>
                    </p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{recipient_name}}, {{closing_id}}, {{property_ref}}, {{property_title}}, {{client_names}}, {{deal_value}}, {{expected_closing_date}}, {{closing_link}}, {{company_name}}',
            ],
            [
                'title' => 'Closing Status Change Notification',
                'slug' => Constants::EMAIL_SLUGS['closing']['closing_status_change'],
                'subject' => 'Closing Status Changed - {{closing_name}} - {{closing_id}}',
                'emailContent' => '
                    <p>Hello {{recipient_name}},</p>
                    <p>A closing status has been updated.</p>
                    <p>
                        <strong>Closing:</strong> {{closing_name}}<br>
                        <strong>Reference:</strong> {{closing_id}}<br>
                        <strong>Property:</strong> {{property_title}}<br>
                        <strong>New Status:</strong> {{status}}<br>
                        <strong>Changed By:</strong> {{changed_by}}<br>
                        <strong>Date:</strong> {{changed_date}}
                    </p>
                    <p>
                        <a href="{{closing_link}}" style="display:inline-block;padding:12px 24px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            View Closing
                        </a>
                    </p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{recipient_name}}, {{closing_name}}, {{closing_id}}, {{property_title}}, {{status}}, {{changed_by}}, {{changed_date}}, {{closing_link}}, {{company_name}}',
            ],
            [
                'title' => 'Task Status Change Notification',
                'slug' => Constants::EMAIL_SLUGS['task']['task_status_change'],
                'subject' => 'Task Status Changed - {{task_title}}',
                'emailContent' => '
                    <p>Hello {{recipient_name}},</p>
                    <p>A task status has been updated.</p>
                    <p>
                        <strong>Task:</strong> {{task_title}}<br>
                        <strong>New Status:</strong> {{status}}<br>
                        <strong>Changed By:</strong> {{changed_by}}<br>
                        <strong>Date:</strong> {{changed_date}}
                    </p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{recipient_name}}, {{task_title}}, {{status}}, {{changed_by}}, {{changed_date}}, {{company_name}}',
            ],
            [
                'title' => 'Overdue Follow-Ups Summary (Manager)',
                'slug' => Constants::EMAIL_SLUGS['task']['overdue_followups'],
                'subject' => 'Overdue Follow-Ups Summary - {{agent_count}} agent(s)',
                'emailContent' => '
                    <p>Hello {{manager_name}},</p>
                    <p>The following agents have overdue follow-ups beyond the agreed threshold of <strong>{{threshold}}</strong>. Please review and follow up.</p>
                    {{followups_table}}
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{manager_name}}, {{threshold}}, {{agent_count}}, {{followups_table}}, {{company_name}}',
            ],
            [
                'title' => 'Missed Opportunity - Enquiry Lost to Colleague Closing',
                'slug' => Constants::EMAIL_SLUGS['enquiry']['lost_colleague'],
                'subject' => 'Missed Opportunity - {{enquiry_reference}} closed by {{closed_by_agent}}',
                'emailContent' => '
                    <p>Hello {{recipient_name}},</p>
                    <p>Missed Opportunity: enquiry <strong>{{enquiry_reference}}</strong> has been closed by <strong>{{closed_by_agent}}</strong>. Please review the activity and follow-up timeline.</p>
                    <p>
                        <strong>Enquiry Reference:</strong> {{enquiry_reference}}<br>
                        <strong>Client:</strong> {{client_name}}<br>
                        <strong>Enquiry Type:</strong> {{enquiry_type}}<br>
                        <strong>Closed By:</strong> {{closed_by_agent}}<br>
                        <strong>Property Closed:</strong> {{property_reference}}<br>
                        <strong>Closing Date:</strong> {{closing_date}}<br>
                        <strong>Original Agent:</strong> {{original_agent}}<br>
                        <strong>Last Activity (Original Agent):</strong> {{last_activity_date}}<br>
                        <strong>Time Gap (last activity to closing):</strong> {{time_gap}}
                    </p>
                    <p><a href="{{enquiry_link}}">View Enquiry</a></p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{recipient_name}}, {{enquiry_reference}}, {{client_name}}, {{enquiry_type}}, {{closed_by_agent}}, {{property_reference}}, {{closing_date}}, {{original_agent}}, {{last_activity_date}}, {{time_gap}}, {{enquiry_link}}, {{company_name}}',
            ],
            [
                'title' => 'Daily Open Game Reminder (Agent)',
                'slug' => Constants::EMAIL_SLUGS['enquiry']['daily_open_game_reminder'],
                'subject' => 'Action Needed: {{enquiry_count}} enquiry(ies) approaching Open Game',
                'emailContent' => '
                    <p>Hello {{agent_name}},</p>
                    <p>The following enquiries assigned to you are in <strong>Property Search</strong> or <strong>Thinking About It</strong> and will move to <strong>Open Game</strong> unless you log meaningful activity before the deadline.</p>
                    {{enquiry_table}}
                    <p>Please follow up and log activity to retain these enquiries.</p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{agent_name}}, {{enquiry_count}}, {{enquiry_table}}, {{company_name}}',
            ],
            [
                'title' => 'Enquiry Inactivity Warning',
                'slug' => Constants::EMAIL_SLUGS['enquiry']['inactivity_warning'],
                'subject' => 'Inactivity Warning - {{contact_name}} - {{reference_no}}',
                'emailContent' => '
                    <p>Hello {{name}},</p>
                    <p>This is a warning that there has been no activity on the following enquiry for over <strong>{{inactivity_hours}} hours</strong>.</p>
                    <p>
                        <strong>Contact:</strong> {{contact_name}}<br>
                        <strong>Reference:</strong> {{reference_no}}<br>
                        <strong>Status:</strong> {{enquiry_status}}<br>
                        <strong>Reason:</strong> {{reason}}
                    </p>
                    <p>Please follow up on this enquiry at the earliest to avoid it being moved to Open Game.</p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{name}}, {{email}}, {{contact_name}}, {{reference_no}}, {{enquiry_status}}, {{inactivity_hours}}, {{reason}}, {{company_name}}',
            ],
            [
                'title' => 'Listing Expiry Reminder - 15 Days',
                'slug' => Constants::EMAIL_SLUGS['property']['listing_expiry_reminder'],
                'subject' => 'Listings Expiring on {{expiry_date}}',
                'emailContent' => '
                    <p>Hello {{admin_name}},</p>

                    <p>The following property listings are scheduled to expire on <strong>{{expiry_date}}</strong> (15 days from today). Please review and take the necessary action.</p>

                    {{property_table_rows}}

                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{admin_name}}, {{expiry_date}}, {{property_table_rows}}, {{company_name}}',
            ],
            [
                'title' => 'Pocket Listing Older Than 2 Weeks',
                'slug' => Constants::EMAIL_SLUGS['property']['pocket_listing_aged'],
                'subject' => 'Pocket Listing Review Required - {{listing_reference}}',
                'emailContent' => '
                    <p>Hello {{recipient_name}},</p>

                    <p>Pocket listing review required: <strong>{{listing_reference}}</strong> has been in Pocket status for {{days_in_pocket}} days.</p>

                    <p>
                        <strong>Listing Reference:</strong> {{listing_reference}}<br>
                        <strong>Property Title:</strong> {{property_title}}<br>
                        <strong>Agent:</strong> {{agent_name}}<br>
                        <strong>Created Date:</strong> {{created_date}}<br>
                        <strong>Current Status:</strong> {{current_status}}<br>
                        <strong>Recommended Action:</strong> {{recommended_action}}
                    </p>

                    <p>
                        <a href="{{property_link}}" style="display:inline-block;padding:12px 24px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            View Listing
                        </a>
                    </p>

                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{recipient_name}}, {{listing_reference}}, {{property_title}}, {{agent_name}}, {{created_date}}, {{current_status}}, {{days_in_pocket}}, {{recommended_action}}, {{property_link}}, {{company_name}}',
            ],
            [
                'title' => 'Upcoming Listing Older Than 2 Weeks',
                'slug' => Constants::EMAIL_SLUGS['property']['upcoming_listing_aged'],
                'subject' => 'Upcoming Listing Review Required - {{listing_reference}}',
                'emailContent' => '
                    <p>Hello {{recipient_name}},</p>

                    <p>Upcoming listing review required: <strong>{{listing_reference}}</strong> has been in Upcoming status for {{days_in_status}} days.</p>

                    <p>
                        <strong>Listing Reference:</strong> {{listing_reference}}<br>
                        <strong>Property Title:</strong> {{property_title}}<br>
                        <strong>Agent:</strong> {{agent_name}}<br>
                        <strong>Created Date:</strong> {{created_date}}<br>
                        <strong>Current Status:</strong> {{current_status}}<br>
                        <strong>Recommended Action:</strong> {{recommended_action}}
                    </p>

                    <p>
                        <a href="{{property_link}}" style="display:inline-block;padding:12px 24px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            View Listing
                        </a>
                    </p>

                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{recipient_name}}, {{listing_reference}}, {{property_title}}, {{agent_name}}, {{created_date}}, {{current_status}}, {{days_in_status}}, {{recommended_action}}, {{property_link}}, {{company_name}}',
            ],
            [
                'title' => 'Boost Request - Admin & Manager Notification',
                'slug' => Constants::EMAIL_SLUGS['boost']['boost_request_admin'],
                'subject' => 'Boost Request - {{property_title}} - {{property_id}}',
                'emailContent' => '
                    <p>Hello {{recipient_name}},</p>
                    <p>A new boost request has been submitted by an agent.</p>
                    <p>
                        <strong>Property Title:</strong> {{property_title}}<br>
                        <strong>Reference Number:</strong> {{property_id}}<br>
                        <strong>Portal:</strong> {{portal}}<br>
                        <strong>Boost Type:</strong> {{boost_type}}<br>
                        <strong>Duration:</strong> {{duration}} Days<br>
                        <strong>Credits:</strong> {{credits}}<br>
                        <strong>Requested By:</strong> {{requested_by}}<br>
                        <strong>Date:</strong> {{requested_date}}
                    </p>
                    <p>
                        <a href="{{property_link}}" style="display:inline-block;padding:12px 24px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            View Property
                        </a>
                    </p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{recipient_name}}, {{property_title}}, {{property_id}}, {{portal}}, {{boost_type}}, {{duration}}, {{credits}}, {{requested_by}}, {{requested_date}}, {{property_link}}, {{company_name}}',
            ],
            [
                'title' => 'Boost Status Change - Agent Notification',
                'slug' => Constants::EMAIL_SLUGS['boost']['boost_status_agent'],
                'subject' => 'Boost {{status}} - {{property_title}} - {{property_id}}',
                'emailContent' => '
                    <p>Hello {{agent_name}},</p>
                    <p>{{status_message}}</p>
                    <p>
                        <strong>Property Title:</strong> {{property_title}}<br>
                        <strong>Reference Number:</strong> {{property_id}}<br>
                        <strong>Portal:</strong> {{portal}}<br>
                        <strong>Duration:</strong> {{duration}} Days<br>
                        <strong>Status:</strong> {{status}}<br>
                        <strong>Updated By:</strong> {{updated_by}}<br>
                        <strong>Date:</strong> {{updated_date}}
                    </p>
                    <p>
                        <a href="{{property_link}}" style="display:inline-block;padding:12px 24px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;">
                            View Property
                        </a>
                    </p>
                    <p>Thank you,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{agent_name}}, {{property_title}}, {{property_id}}, {{portal}}, {{duration}}, {{status}}, {{status_message}}, {{updated_by}}, {{updated_date}}, {{property_link}}, {{company_name}}',
            ],
            [
                'title' => 'Agent Target Performance Summary',
                'slug' => Constants::EMAIL_SLUGS['performance']['target_summary'],
                'subject' => '{{period_type}} Performance Summary - {{period_label}}',
                'emailContent' => '
                    <p>Hello {{agent_name}},</p>
                    <p>Here is your {{period_type}} performance summary for <strong>{{period_label}}</strong> ({{period_range}}), measured against your {{period_type}} targets.</p>
                    {{performance_table}}
                    <p>One or more metrics are below target. Please review and plan your actions for the upcoming {{period_type}} period.</p>
                    <p>Regards,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{agent_name}}, {{period_type}}, {{period_label}}, {{period_range}}, {{performance_table}}, {{company_name}}',
            ],
            [
                'title' => 'Manager Target Performance Alert',
                'slug' => Constants::EMAIL_SLUGS['performance']['manager_summary'],
                'subject' => '{{period_type}} KPI Alert - {{agent_count}} agent(s) below target - {{period_label}}',
                'emailContent' => '
                    <p>Hello {{manager_name}},</p>
                    <p>{{agent_count}} agent(s) finished <strong>{{period_label}}</strong> ({{period_range}}) below one or more of their {{period_type}} targets.</p>
                    {{performance_table}}
                    <p>Please review and follow up with the relevant agents for the upcoming {{period_type}} period.</p>
                    <p>Regards,<br><strong>{{company_name}}</strong></p>
                ',
                'macro' => '{{manager_name}}, {{period_type}}, {{period_label}}, {{period_range}}, {{agent_count}}, {{performance_table}}, {{company_name}}',
            ],
            [
                'title' => 'Employee Birthday Greeting',
                'slug' => Constants::EMAIL_SLUGS['hr']['employee_birthday'],
                'subject' => 'Happy Birthday {{employee_name}}! 🎉',
                'emailContent' => '
                    <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; background: #ffffff;">
                        <h2 style="color: #2563eb; margin-top: 0;">🎂 Happy Birthday, {{employee_name}}! 🎈</h2>
                        <p>Dear {{employee_name}},</p>
                        <p>On behalf of the entire team at <strong>{{company_name}}</strong>, we want to wish you a very Happy Birthday!</p>
                        <p>We appreciate your dedication, hard work, and the positive energy you bring to our team every day.</p>
                        <p>May this year bring you great happiness, good health, and continued success in all your endeavors.</p>
                        <br>
                        <p>Enjoy your special day!</p>
                        <p>Warmest regards,<br><strong>{{company_name}} Team</strong></p>
                    </div>
                ',
                'macro' => '{{employee_name}}, {{employee_first_name}}, {{employee_role}}, {{employee_department}}, {{company_name}}, {{company_email}}, {{year}}',
            ],
            [
                'title' => 'Employee Holiday Greeting',
                'slug' => Constants::EMAIL_SLUGS['hr']['employee_holiday'],
                'subject' => 'Warm Holiday Wishes from {{company_name}}! 🎄✨',
                'emailContent' => '
                    <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; background: #ffffff;">
                        <h2 style="color: #059669; margin-top: 0;">🌟 Season’s Greetings & Happy Holidays! 🌟</h2>
                        <p>Dear {{employee_name}},</p>
                        <p>As we celebrate this festive season, we want to extend our heartfelt gratitude for your dedication and contributions to <strong>{{company_name}}</strong>.</p>
                        <p>May this holiday season bring you, your family, and loved ones peace, joy, and wonderful moments together.</p>
                        <p>Wishing you a joyful holiday and a happy, prosperous year ahead!</p>
                        <br>
                        <p>Warmest wishes,<br><strong>Management & Team at {{company_name}}</strong></p>
                    </div>
                ',
                'macro' => '{{employee_name}}, {{employee_first_name}}, {{holiday_name}}, {{company_name}}, {{year}}',
            ],
        ];
        foreach ($emails as $email) {
            ManageEmail::updateOrCreate(
                ['slug' => $email['slug']],
                array_merge($email, [
                    // Canonical lowercase key — MailService::sendEmail only sends
                    // when status === 'active' (see the convert_status_values_to_keys migration).
                    'status' => Constants::USER_STATUS_ACTIVE,
                    'createdBy' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
