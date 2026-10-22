<?php
// reusables/organization_requirements.php
//
// Organization types and the documents each must submit when applying
// (organizations/applications/create.php; listed for the form by
// organizations/applications/requirements.php). `key` is stored as
// organization_documents.doc_type and is also the multipart field name.
//
// Dasmariñas has ONE LGU: `city_rescue` ("City Rescue / LGU") may exist only
// once (any non-rejected organization of that type counts, even an inactive
// one), checked on application and on approval under the named lock
// LGU_LOCK_NAME. The LGU is the only type the DSS sends citywide.
// Labels for these keys on the web: dascare/src/config/organizationDocuments.js.

const LGU_ORGANIZATION_TYPE = 'city_rescue';
const LGU_LOCK_NAME = 'dascare_single_lgu';

const ORGANIZATION_TYPES = [
    'city_rescue' => 'City Rescue / LGU',
    'barangay_rescue' => 'Barangay Rescue Unit',
    'hospital' => 'Hospital Ambulance Service',
    'private_ambulance' => 'Private Ambulance Provider',
    'other' => 'Other Emergency Service',
];

const AMBULANCE_PAPERS = ['key' => 'ambulance_papers', 'label' => 'Ambulance unit papers', 'hint' => 'LTO registration / DOH ambulance accreditation of the units', 'required' => false];
const REPRESENTATIVE_ID = ['key' => 'representative_id', 'label' => "Representative's government ID", 'hint' => 'Valid government-issued ID of the authorized representative', 'required' => true];

const ORGANIZATION_DOCUMENT_REQUIREMENTS = [
    'city_rescue' => [
        ['key' => 'lgu_executive_order', 'label' => 'Executive Order / City Ordinance', 'hint' => 'Creating or designating the city rescue / DRRM office (e.g. CDRRMO)', 'required' => true],
        ['key' => 'lgu_designation', 'label' => 'Designation of representative', 'hint' => "Mayor's appointment / designation of the office head or authorized representative", 'required' => true],
        REPRESENTATIVE_ID,
        AMBULANCE_PAPERS,
    ],
    'barangay_rescue' => [
        ['key' => 'barangay_resolution', 'label' => 'Barangay resolution', 'hint' => 'Creating or authorizing the barangay rescue unit', 'required' => true],
        ['key' => 'barangay_designation', 'label' => 'Punong Barangay designation', 'hint' => 'Designation of the authorized representative, signed by the Punong Barangay', 'required' => true],
        REPRESENTATIVE_ID,
        AMBULANCE_PAPERS,
    ],
    'hospital' => [
        ['key' => 'doh_license', 'label' => 'DOH License to Operate', 'hint' => "The hospital's current DOH License to Operate", 'required' => true],
        ['key' => 'authorization_letter', 'label' => 'Authorization letter', 'hint' => 'From the hospital director / administrator, naming the representative', 'required' => true],
        REPRESENTATIVE_ID,
        AMBULANCE_PAPERS,
    ],
    'private_ambulance' => [
        ['key' => 'business_registration', 'label' => 'DTI / SEC registration', 'hint' => 'Certificate of business name (DTI) or SEC registration', 'required' => true],
        ['key' => 'mayors_permit', 'label' => "Mayor's / business permit", 'hint' => 'Current-year business permit', 'required' => true],
        ['key' => 'doh_ambulance_license', 'label' => 'DOH ambulance service license', 'hint' => 'DOH license / accreditation to operate an ambulance service', 'required' => true],
        ['key' => 'ambulance_papers', 'label' => 'LTO registration of units', 'hint' => 'Official receipt / certificate of registration of the ambulances', 'required' => false],
    ],
    'other' => [
        ['key' => 'registration_proof', 'label' => 'Proof of registration / authorization', 'hint' => 'Registration, accreditation or authorization to provide emergency services', 'required' => true],
        REPRESENTATIVE_ID,
        ['key' => 'supporting_document', 'label' => 'Additional document', 'hint' => 'Anything else that supports the application', 'required' => false],
    ],
];

/** Is the single LGU slot taken? Any non-rejected, non-deleted city_rescue organization (optionally ignoring one). */
function organizationLguTaken(PDO $pdo, ?int $exceptOrganizationId = null): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM organizations WHERE organization_type = ? AND deleted_at IS NULL
        AND (application_status IS NULL OR application_status <> 'rejected') AND id <> ? LIMIT 1");
    $stmt->execute([LGU_ORGANIZATION_TYPE, (int) $exceptOrganizationId]);
    return (bool) $stmt->fetchColumn();
}

const LGU_TAKEN_MESSAGE = 'Dasmariñas already has its LGU account (City Rescue / LGU). Choose another organization type, or contact the DASCARE platform administrator.';
