/**
 * Labels for organization_documents.doc_type — the per-type requirement keys
 * from dascare_api/reusables/organization_requirements.php plus the three
 * generic keys older applications used. Keep in sync with that file.
 * Same file in dascare_mobile/src/config/organizationDocuments.js.
 */
export const ORGANIZATION_DOCUMENT_LABELS = {
  lgu_executive_order: 'Executive Order / City Ordinance',
  lgu_designation: 'Designation of representative',
  barangay_resolution: 'Barangay resolution',
  barangay_designation: 'Punong Barangay designation',
  doh_license: 'DOH License to Operate',
  authorization_letter: 'Authorization letter',
  business_registration: 'DTI / SEC registration',
  mayors_permit: "Mayor's / business permit",
  doh_ambulance_license: 'DOH ambulance service license',
  registration_proof: 'Proof of registration / authorization',
  representative_id: "Representative's government ID",
  ambulance_papers: 'Ambulance unit papers',
  // Older applications (before per-type requirements)
  registration_document: 'Registration / authorization',
  operating_authority: 'Operating / accreditation',
  supporting_document: 'Additional document',
}

export const organizationDocumentLabel = (type) =>
  ORGANIZATION_DOCUMENT_LABELS[type] || String(type || 'Document').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())
