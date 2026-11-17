<?php

// Council-only display names. A few members are written differently in the member list than the party
// uses for them (a different surname, for example). The member list itself is owned and overwritten by the
// party's external system, so it is never edited; the name shown on the council pages (roster, meetings,
// live board, attendance exports, QR scan result) and used to find the Representatives picture comes from
// here instead. It does not change the member list or what the rest of the app shows.
//
//   'member id' => 'name to show on the council pages',
return [
    '400300136' => 'مارتين نجم كتيلي',   // مارتين بدري نجم in the member list
];
