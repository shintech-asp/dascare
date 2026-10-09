// Fallback copy for the Terms of Service and Privacy Policy screens, extracted
// verbatim from the web pages (dascare/src/views/pages/TermsOfService.vue and
// PrivacyPolicy.vue). Like the web, LegalView first loads the current text from
// page-content.php and only shows this when offline or if that fails.

export const LEGAL = {
  "terms-of-service": {
    "title": "Terms of Service",
    "heading": "The rules for using DASCARE.",
    "intro": "These terms cover citizens, guests, and staff of verified ambulance organizations using DASCARE for Dasmariñas City.",
    "updated": "August 6, 2026",
    "sections": [
      {
        "id": "acceptance",
        "num": "01",
        "title": "Acceptance of terms",
        "paragraphs": [
          "By creating an account, submitting a guest request, or otherwise using DASCARE, you agree to these Terms of Service and to the Privacy Policy. If you are using DASCARE on behalf of an ambulance organization, you also agree on behalf of that organization, and confirm you are authorized to do so.",
          "If you do not agree with these terms, do not use DASCARE — you can still contact your local emergency hotline directly."
        ]
      },
      {
        "id": "who-can-use",
        "num": "02",
        "title": "Who can use DASCARE",
        "paragraphs": [
          "DASCARE is built for residents, guests, and verified rescue personnel operating within Dasmariñas City. Citizen accounts require identity verification before certain features unlock. Guests may submit emergency and standard requests without an account, subject to the guest safeguards described below.",
          "Ambulance organizations must apply and be approved by platform administrators before their staff, vehicles, and schedules can appear in DASCARE. Approval can be suspended or withdrawn for non-compliance with applicable regulations or these terms."
        ]
      },
      {
        "id": "emergency-scope",
        "num": "03",
        "title": "Emergency use and limitations",
        "paragraphs": [
          "DASCARE is a coordination tool, not an emergency hotline and not a substitute for one. In a life-threatening situation, contact your local emergency hotline first; use DASCARE to coordinate ambulance response alongside that call, not instead of it.",
          "DASCARE's decision support system ranks the most suitable available organization and ambulance based on distance, readiness, capability, and workload. This ranking is a recommendation. A human dispatcher at the receiving organization must still accept the incident and assign the ambulance and crew — DASCARE does not dispatch automatically, and cannot guarantee response time, ambulance availability, or outcome."
        ]
      },
      {
        "id": "guest-requests",
        "num": "04",
        "title": "Guest requests",
        "paragraphs": [
          "Guests may submit requests without registering an account. A genuine emergency is never blocked by guest limits — after a device or phone number sends a number of guest requests in a day, further requests still go through, but receive an additional dispatcher check to guard against misuse.",
          "Guest requests are not saved to any account. The reference number issued at submission is the only way to follow up on a guest request; DASCARE cannot recover it if lost."
        ]
      },
      {
        "id": "responsibilities",
        "num": "05",
        "title": "Your responsibilities",
        "list": [
          "Provide accurate location, contact, and situation information when submitting a request.",
          "Use DASCARE only for genuine emergencies, legitimate scheduled transport, or authorized operational purposes.",
          "Keep your account credentials confidential and notify us of any unauthorized use.",
          "Do not submit false, exaggerated, or duplicate reports — repeated suspected abuse may be reviewed and can result in account restrictions."
        ]
      },
      {
        "id": "organization-responsibilities",
        "num": "06",
        "title": "Organization responsibilities",
        "paragraphs": [
          "Verified organizations are responsible for keeping their fleet, staff, and schedule information current, for responding to offered incidents in good faith, and for maintaining the credentials and compliance documents required for continued approval.",
          "Organizations manage access within their own account through role-based permissions, and are responsible for the actions of staff accounts they create and authorize."
        ]
      },
      {
        "id": "location-tracking",
        "num": "07",
        "title": "Location and tracking data",
        "paragraphs": [
          "To coordinate a response, DASCARE collects the location submitted with a request and, during an active mission, the live location of the assigned ambulance. This data is used for dispatch, tracking, handoff coordination, and safety analytics, and is handled as described in the Privacy Policy."
        ]
      },
      {
        "id": "availability",
        "num": "08",
        "title": "Service availability",
        "paragraphs": [
          "DASCARE is provided on an \"as available\" basis. As an academic capstone system, it does not carry the uptime guarantees of a commercial emergency dispatch platform. Connectivity issues, device limitations, or maintenance may affect availability — this is one more reason to treat official emergency hotlines as the primary channel for life-threatening situations."
        ]
      },
      {
        "id": "liability",
        "num": "09",
        "title": "Limitation of liability",
        "paragraphs": [
          "DASCARE, its developers, and participating organizations are not liable for delays, unavailability, or outcomes arising from use of the platform, to the fullest extent permitted by law. Nothing in these terms limits liability that cannot be limited under applicable Philippine law."
        ]
      },
      {
        "id": "termination",
        "num": "10",
        "title": "Suspension and termination",
        "paragraphs": [
          "We may suspend or terminate access for accounts or organizations that violate these terms, misuse the platform, or pose a risk to other users, with notice where practicable. You may stop using DASCARE and request account closure at any time."
        ]
      },
      {
        "id": "changes",
        "num": "11",
        "title": "Changes to these terms",
        "paragraphs": [
          "We may update these terms as DASCARE evolves. Material changes will be reflected here with an updated date; continued use after changes take effect means you accept the revised terms."
        ]
      },
      {
        "id": "governing-law",
        "num": "12",
        "title": "Governing law",
        "paragraphs": [
          "These terms are governed by the laws of the Republic of the Philippines, without regard to conflict-of-law principles."
        ]
      }
    ]
  },
  "privacy-policy": {
    "title": "Privacy Policy",
    "heading": "What we collect, and why.",
    "intro": "DASCARE handles time-sensitive personal and location data to coordinate emergency response. This policy explains what's collected, who can see it, and the choices you have.",
    "updated": "August 6, 2026",
    "sections": [
      {
        "id": "scope",
        "num": "01",
        "title": "Scope of this policy",
        "paragraphs": [
          "This policy applies to citizens, guests, and organization staff using DASCARE's web and mobile system for Dasmariñas City, and describes how we collect, use, share, and protect personal data in connection with emergency and scheduled ambulance coordination.",
          "We process data in line with the Philippine Data Privacy Act of 2012 (RA 10173) and its implementing rules."
        ]
      },
      {
        "id": "information-we-collect",
        "num": "02",
        "title": "Information we collect",
        "paragraphs": [
          "Depending on how you use DASCARE, we collect:"
        ],
        "list": [
          "Account details — name, contact number, email, and identity verification information for citizen accounts.",
          "Request details — the reported location, situation description, and, for guests, the name and phone number provided at submission.",
          "Location and tracking data — the coordinates attached to a request, and the live position of an assigned ambulance during an active mission.",
          "Limited prehospital information — vital signs, observations, and interventions recorded by responders for a specific incident, not a full medical history.",
          "Operational data from organizations — staff duty status, vehicle and crew assignments, and mission records.",
          "System data — device information, login activity, and audit logs of actions taken within the platform."
        ]
      },
      {
        "id": "how-we-use-it",
        "num": "03",
        "title": "How we use this information",
        "list": [
          "To route a request to the decision support system, which ranks suitable organizations and ambulances by distance, readiness, capability, and workload.",
          "To let a dispatcher accept an incident and assign an ambulance and crew.",
          "To provide live mission tracking and status updates to the requester and assigned organization.",
          "To support limited hospital endorsement and handoff at the point of transfer.",
          "To generate analytics, incident heatmaps, and performance reporting at an aggregate level.",
          "To maintain audit logs for accountability, dispute review, and security.",
          "To operate guest request safeguards, which apply an additional dispatcher check after repeated submissions from the same device or number — without ever blocking a genuine emergency."
        ]
      },
      {
        "id": "who-sees-it",
        "num": "04",
        "title": "Who your information is shared with",
        "paragraphs": [
          "Request and location details are shared with the organization and dispatcher assigned to your incident, and with the responding crew, for the duration needed to deliver assistance. Receiving facilities see only the limited handoff information relevant to a transfer, not your full request history.",
          "Platform and organization administrators can access data within their role-based permissions for oversight, compliance, and audit purposes — access is scoped by DASCARE's role-based access control (RBAC), not open to every staff account by default.",
          "We do not sell personal data, and we do not share it with advertisers."
        ]
      },
      {
        "id": "guest-data",
        "num": "05",
        "title": "Guest requests",
        "paragraphs": [
          "Guest requests are not attached to a registered account. The name and phone number you provide are used to process the request, apply guest safeguards, and allow a dispatcher to reach you — they are not retained as part of an ongoing profile."
        ]
      },
      {
        "id": "retention",
        "num": "06",
        "title": "Data retention",
        "paragraphs": [
          "We retain incident, mission, and audit records for as long as needed to support accountability, dispute resolution, analytics, and legal or regulatory requirements, then delete or anonymize them. Account information is retained while your account remains active, and for a limited period after closure where required for legitimate operational or legal purposes."
        ]
      },
      {
        "id": "security",
        "num": "07",
        "title": "How we protect it",
        "paragraphs": [
          "DASCARE restricts access through role-based permissions, keeps a logged audit trail of sensitive actions, and applies technical and organizational safeguards appropriate to the sensitivity of emergency and location data. No system is completely immune to risk, and we work to identify and address vulnerabilities as they are found."
        ]
      },
      {
        "id": "your-rights",
        "num": "08",
        "title": "Your rights",
        "paragraphs": [
          "Subject to applicable law, you may:"
        ],
        "list": [
          "Request access to the personal data we hold about you.",
          "Request correction of inaccurate or outdated information.",
          "Request deletion of your account data, subject to records we must retain for legal or safety reasons.",
          "Object to or request restriction of certain processing.",
          "Lodge a complaint with the National Privacy Commission if you believe your rights have been violated."
        ]
      },
      {
        "id": "children",
        "num": "09",
        "title": "Minors' data",
        "paragraphs": [
          "DASCARE may process data about a minor when they are the patient in an emergency or scheduled transport request submitted by a parent, guardian, or bystander. Such data is used solely to coordinate the response and is subject to the same safeguards as any other request."
        ]
      },
      {
        "id": "changes",
        "num": "10",
        "title": "Changes to this policy",
        "paragraphs": [
          "We may update this policy as DASCARE evolves. Material changes will be reflected here with an updated date."
        ]
      }
    ]
  }
}
