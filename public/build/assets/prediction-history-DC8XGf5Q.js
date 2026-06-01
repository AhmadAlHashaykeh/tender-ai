document.addEventListener("DOMContentLoaded",()=>{typeof lucide<"u"&&lucide.createIcons();const b={1:{date:"2026-05-20",time:"14:32",tender:"Gulf Health Council Tender (GHC)",code:"SA-2026-001",country:"Saudi Arabia",drug:"Paracetamol 500mg",fullQuantity:"500,000 units",recPrice:"4.04",winProb:"76%",risk:"Medium",riskClass:"bg-amber-50 text-amber-700 border-amber-200",status:"Won",actualAward:"4.10",accuracy:"98.5%",awardRange:"$3.80 to $4.56",reasons:["8 historical awards found for Paracetamol 500mg in Saudi Arabia.","Requested quantity (500,000 units) is above historical average lot size — volume discount applied.","Moderate price variability detected — bid positioned near historical median to reduce risk.","Gulf Health Council has 240+ historical records — strong data foundation for this tender.","Analgesics in GCC tenders show consistent multi-year price growth — upward trend factored in."]},2:{date:"2026-05-18",time:"11:15",tender:"UAE Health Authority Quarterly Procurement",code:"UAE-2026-012",country:"United Arab Emirates",drug:"Insulin Glargine 100U/mL Pen",fullQuantity:"50,000 units",recPrice:"32.28",winProb:"71%",risk:"Medium",riskClass:"bg-amber-50 text-amber-700 border-amber-200",status:"Submitted",actualAward:"Pending",accuracy:"N/A",awardRange:"$30.00 to $35.50",reasons:["12 historical awards found for Insulin Glargine in GCC markets.","Cold-chain logistics requirements in high temperature zones increases standard delivery margin by 12%.","Limited competitor field (3 primary suppliers) allows higher pricing ceiling.","Bid positioned at 65th percentile of historical winning prices.","High accuracy confidence level due to stable market demand curves."]},3:{date:"2026-05-15",time:"09:48",tender:"Egypt MOH Central Pharmaceutical Tender",code:"EG-2025-089",country:"Egypt",drug:"Ibuprofen 400mg",fullQuantity:"700,000 units",recPrice:"4.63",winProb:"81%",risk:"Low",riskClass:"bg-emerald-50 text-emerald-700 border-emerald-200",status:"Won",actualAward:"4.75",accuracy:"97.5%",awardRange:"$4.20 to $4.90",reasons:["15 historical awards found for Ibuprofen 400mg in Egypt.","High local production competition requires aggressive pricing to secure volume.","Volume discount of 8% applied for 700K unit lot size.","Bid positioned at 25th percentile of historical winning bids.","Local currency fluctuations factor incorporated into contingency margin."]},4:{date:"2026-05-12",time:"16:05",tender:"NUPCO Annual Antibiotics Tender",code:"SA-NUPCO-2026",country:"Saudi Arabia",drug:"Amoxicillin 250mg",fullQuantity:"300,000 units",recPrice:"8.12",winProb:"74%",risk:"Low",riskClass:"bg-emerald-50 text-emerald-700 border-emerald-200",status:"Lost",actualAward:"7.90",accuracy:"+2.7%",awardRange:"$7.80 to $8.50",reasons:["9 historical awards found for Amoxicillin 250mg in Saudi Arabia.","NUPCO procurement rules award lowest price bid with 10% premium for domestic manufacturers.","Competitor bid aggressively, winning at $7.90 per unit (2.7% below recommendation).","Pricing variance analysis shows strong resistance below $8.00.","Future recommendations adjusted to account for domestic preference discounts."]},5:{date:"2026-05-10",time:"13:22",tender:"Iraq Ministry of Health Q1 Procurement",code:"IQ-2026-034",country:"Iraq",drug:"Omeprazole 20mg",fullQuantity:"200,000 units",recPrice:"7.51",winProb:"62%",risk:"High",riskClass:"bg-red-50 text-red-600 border-red-200",status:"Reviewed",actualAward:"Pending",accuracy:"N/A",awardRange:"$7.00 to $8.20",reasons:["6 historical awards found for Omeprazole 20mg in Iraq.","Re-registration requirements and import duties increase base supply costs by 15%.","High win probability of 62% is supported by lack of local manufacturing alternatives.","Bid positioned near historical GCC median prices.","Security logistics surcharge included in final recommendation."]},6:{date:"2026-05-08",time:"10:44",tender:"Jordan MOH Central Procurement Tender",code:"JO-MOH-2026",country:"Jordan",drug:"Atorvastatin 20mg",fullQuantity:"150,000 units",recPrice:"9.44",winProb:"58%",risk:"High",riskClass:"bg-red-50 text-red-600 border-red-200",status:"Generated",actualAward:"Pending",accuracy:"N/A",awardRange:"$9.00 to $10.10",reasons:["11 historical awards found for Atorvastatin 20mg in Jordan.","Jordan MOH procurement values technical score (70%) over financial score (30%).","Bid recommendation positioned at a premium due to high quality validation files.","Win probability (58%) is lower due to price-sensitive competitors.","Post-recommendation review confirms high margin safety."]},7:{date:"2026-05-05",time:"15:30",tender:"Kuwait MOH Specialty Pharmaceuticals Tender",code:"KW-MOH-2025",country:"Kuwait",drug:"Insulin Glargine 100U/mL Pen",fullQuantity:"60,000 units",recPrice:"27.46",winProb:"68%",risk:"Medium",riskClass:"bg-amber-50 text-amber-700 border-amber-200",status:"Pending",actualAward:"Pending",accuracy:"N/A",awardRange:"$25.50 to $29.00",reasons:["5 historical awards found for Insulin Glargine in Kuwait.","Price control updates in Kuwait MOH favor consolidated multi-year bid agreements.","Kuwait MOH offers premium pricing for long-term supply guarantees.","Bid recommendation incorporates GCC reference prices.","Low volume volatility simplifies forecast calculations."]}};let s="All",d="All",u="";const f=document.getElementById("search-predictions"),m=document.getElementById("predictions-tbody").querySelectorAll(".table-body-row"),w=document.getElementById("record-count"),n=document.getElementById("drawer-overlay"),i=document.getElementById("drawer-sidebar");function l(){let t=0;m.forEach(e=>{const a=e.getAttribute("data-status"),r=e.getAttribute("data-risk"),o=e.textContent.toLowerCase(),c=s==="All"||a===s,p=d==="All"||r===d,y=o.includes(u.toLowerCase());c&&p&&y?(e.style.display="",t++):e.style.display="none"}),w.textContent=t}const g=document.querySelectorAll("#status-filter-row button");g.forEach(t=>{t.addEventListener("click",()=>{g.forEach(e=>{e.className="px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-200 bg-white/60 border-border/50 text-foreground/70 hover:border-primary/40 hover:text-primary"}),t.className="px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-200 bg-gradient-to-r from-primary to-secondary text-white border-transparent shadow-sm",s=t.getAttribute("data-status"),l()})});const h=document.querySelectorAll("#risk-filter-row button");h.forEach(t=>{t.addEventListener("click",()=>{h.forEach(e=>{e.className="px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-200 bg-white/60 border-border/50 text-foreground/70 hover:border-primary/40 hover:text-primary"}),t.className="px-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-200 bg-gradient-to-r from-primary to-secondary text-white border-transparent shadow-sm",d=t.getAttribute("data-risk"),l()})}),f.addEventListener("input",t=>{u=t.target.value,l()});function v(t){const e=b[t];if(!e)return;let a="";e.status==="Won"?a=`
                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-foreground uppercase tracking-wide">Historical Comparison</p>
                        <div class="rounded-xl p-4 border-2 bg-emerald-50/60 border-emerald-200">
                            <div class="flex items-center gap-2 mb-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trophy w-4 h-4 text-emerald-600"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"></path></svg>
                                <span class="text-sm font-semibold text-emerald-700">Tender Won</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <p class="text-[10px] text-muted-foreground mb-0.5">Our Bid</p>
                                    <p class="text-sm font-bold text-foreground">$${e.recPrice}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-muted-foreground mb-0.5">Actual Award</p>
                                    <p class="text-sm font-bold text-emerald-600">$${e.actualAward}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-muted-foreground mb-0.5">Accuracy</p>
                                    <p class="text-sm font-bold text-foreground">${e.accuracy}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `:e.status==="Lost"?a=`
                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-foreground uppercase tracking-wide">Historical Comparison</p>
                        <div class="rounded-xl p-4 border-2 bg-red-50 border-red-200">
                            <div class="flex items-center gap-2 mb-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-x w-4 h-4 text-red-600"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>
                                <span class="text-sm font-semibold text-red-700" style="color: #dc2626;">Tender Lost</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <p class="text-[10px] text-muted-foreground mb-0.5">Our Bid</p>
                                    <p class="text-sm font-bold text-foreground">$${e.recPrice}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-muted-foreground mb-0.5">Winning Bid</p>
                                    <p class="text-sm font-bold text-red-600" style="color: #dc2626;">$${e.actualAward}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-muted-foreground mb-0.5">Difference</p>
                                    <p class="text-sm font-bold text-foreground">${e.accuracy}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `:a=`
                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-foreground uppercase tracking-wide">Historical Comparison</p>
                        <div class="rounded-xl p-4 border bg-muted/30 border-border/40">
                            <div class="flex items-center gap-2" style="color: var(--muted-foreground);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock w-4 h-4"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                <span class="text-sm font-semibold">Comparison Pending Award</span>
                            </div>
                            <p class="text-[10px] text-muted-foreground mt-1">This tender is currently in the ${e.status.toLowerCase()} stage. Historical comparison will be available once the final award price is announced.</p>
                        </div>
                    </div>
                `;let r="";e.status==="Won"?r='<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trophy w-3 h-3"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"></path></svg>Won</span>':e.status==="Lost"?r='<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border bg-red-50 text-red-600 border-red-200"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-x w-3 h-3"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>Lost</span>':e.status==="Submitted"?r='<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border bg-violet-50 text-violet-700 border-violet-200"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-stack w-3 h-3"><path d="M21 7h-3a2 2 0 0 1-2-2V2"></path><path d="M21 6v6.5c0 .8-.7 1.5-1.5 1.5h-7c-.8 0-1.5-.7-1.5-1.5v-9c0-.8.7-1.5 1.5-1.5H17Z"></path><path d="M7 8v8.8c0 .3.2.6.4.8.2.2.5.4.8.4H15"></path><path d="M3 12v8.8c0 .3.2.6.4.8.2.2.5.4.8.4H11"></path></svg>Submitted</span>':e.status==="Reviewed"?r='<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border bg-blue-50 text-blue-700 border-blue-200"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-brain w-3 h-3"><path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"></path><path d="M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 1 1 12 18Z"></path><path d="M15 13a4.5 4.5 0 0 1-3-4 4.5 4.5 0 0 1-3 4"></path><path d="M17.599 6.5a3 3 0 0 0 .399-1.375"></path><path d="M6.003 5.125A3 3 0 0 0 6.401 6.5"></path><path d="M3.477 10.896a4 4 0 0 1 .585-.396"></path><path d="M19.938 10.5a4 4 0 0 1 .585.396"></path><path d="M6 18a4 4 0 0 1-1.967-.516"></path><path d="M19.967 17.484A4 4 0 0 1 18 18"></path></svg>Reviewed</span>':e.status==="Generated"?r='<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border bg-slate-50 text-slate-600 border-slate-200"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles w-3 h-3"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"></path><path d="M20 3v4"></path><path d="M22 5h-4"></path><path d="M4 17v2"></path><path d="M5 18H3"></path></svg>Generated</span>':r='<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border bg-amber-50 text-amber-700 border-amber-200"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock w-3 h-3"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>Pending</span>';let o="";e.reasons.forEach((c,p)=>{o+=`
                    <li class="flex items-start gap-2.5 text-xs text-foreground/80">
                        <span class="w-5 h-5 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0 text-[10px] font-bold mt-0.5">${p+1}</span>
                        ${c}
                    </li>
                `}),i.innerHTML=`
                <div class="flex items-center justify-between p-5 border-b border-border/40 bg-gradient-to-r from-primary/5 to-secondary/5">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary to-secondary flex items-center justify-center shadow-md">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles w-4 h-4 text-white"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"></path><path d="M20 3v4"></path><path d="M22 5h-4"></path><path d="M4 17v2"></path><path d="M5 18H3"></path></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-foreground text-sm" style="margin: 0;">Prediction Details</p>
                            <p class="text-[10px] text-muted-foreground" style="margin: 0;">${e.date} ${e.time}</p>
                        </div>
                    </div>
                    <button class="w-8 h-8 rounded-lg hover:bg-muted/60 flex items-center justify-center transition-colors" id="drawer-close-btn" style="border: none; background: none; cursor: pointer;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x w-4 h-4 text-muted-foreground"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto p-5 space-y-5">
                    <div class="space-y-3">
                        <div class="p-4 rounded-xl bg-muted/30 border border-border/40 space-y-2.5">
                            <div>
                                <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium mb-0.5" style="margin: 0;">Tender</p>
                                <p class="text-sm font-semibold text-foreground" style="margin: 0; margin-top: 0.125rem;">${e.tender}</p>
                                <p class="text-[10px] text-muted-foreground font-mono" style="margin: 0; margin-top: 0.125rem;">${e.code}</p>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs text-muted-foreground" style="display: flex; align-items: center; gap: 0.375rem; margin-top: 0.5rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-pin w-3.5 h-3.5"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                ${e.country}
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <div class="p-3.5 rounded-xl bg-muted/30 border border-border/40">
                                <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium mb-1" style="margin: 0;">Drug</p>
                                <p class="text-xs font-semibold text-foreground" style="margin: 0; margin-top: 0.25rem;">${e.drug}</p>
                            </div>
                            <div class="p-3.5 rounded-xl bg-muted/30 border border-border/40">
                                <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium mb-1" style="margin: 0;">Quantity</p>
                                <p class="text-xs font-semibold text-foreground" style="margin: 0; margin-top: 0.25rem;">${e.fullQuantity}</p>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-2" style="margin-top: 1.25rem;">
                        <p class="text-xs font-semibold text-foreground uppercase tracking-wide" style="margin: 0; margin-bottom: 0.5rem;">Prediction Output</p>
                        <div class="grid grid-cols-2 gap-3" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <div class="p-3.5 rounded-xl bg-gradient-to-br from-primary/8 to-secondary/8 border border-primary/15" style="background: linear-gradient(135deg, rgba(13, 133, 230, 0.08), rgba(124, 58, 237, 0.08)); border-color: rgba(13, 133, 230, 0.15);">
                                <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium mb-1" style="margin: 0;">Recommended Bid</p>
                                <p class="text-xl font-bold text-primary" style="margin: 0; margin-top: 0.25rem;">$${e.recPrice}</p>
                                <p class="text-[10px] text-muted-foreground" style="margin: 0; margin-top: 0.125rem;">per unit</p>
                            </div>
                            <div class="p-3.5 rounded-xl bg-muted/30 border border-border/40">
                                <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium mb-1" style="margin: 0;">Award Range</p>
                                <p class="text-sm font-bold text-foreground" style="margin: 0; margin-top: 0.25rem;">$${e.awardRange.split(" to ")[0].replace("$","")}</p>
                                <p class="text-[10px] text-muted-foreground" style="margin: 0; margin-top: 0.125rem;">to $${e.awardRange.split(" to ")[1]?e.awardRange.split(" to ")[1].replace("$",""):""}</p>
                            </div>
                            <div class="p-3.5 rounded-xl bg-muted/30 border border-border/40">
                                <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium mb-1" style="margin: 0;">Prob. of Win</p>
                                <p class="text-xl font-bold text-foreground" style="margin: 0; margin-top: 0.25rem;">${e.winProb}</p>
                            </div>
                            <div class="p-3.5 rounded-xl bg-muted/30 border border-border/40 flex flex-col gap-1.5" style="display: flex; flex-direction: column; gap: 0.375rem;">
                                <p class="text-[10px] text-muted-foreground uppercase tracking-wide font-medium" style="margin: 0;">Risk Level</p>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border ${e.riskClass.replace("bg-amber-50 text-amber-700 border-amber-200","risk-medium").replace("bg-emerald-50 text-emerald-700 border-emerald-200","risk-low").replace("bg-red-50 text-red-600 border-red-200","risk-high")}" style="display: inline-flex; align-items: center; justify-content: center; width: fit-content;">${e.risk}</span>
                                <p class="text-[10px] text-muted-foreground" style="margin: 0; margin-top: 0.125rem;">Status: ${r}</p>
                            </div>
                        </div>
                    </div>
                    ${a}
                    <div class="space-y-2" style="margin-top: 1.25rem;">
                        <div class="flex items-center gap-2" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-brain w-4 h-4 text-primary"><path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"></path><path d="M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 1 1 12 18Z"></path><path d="M15 13a4.5 4.5 0 0 1-3-4 4.5 4.5 0 0 1-3 4"></path><path d="M17.599 6.5a3 3 0 0 0 .399-1.375"></path><path d="M6.003 5.125A3 3 0 0 0 6.401 6.5"></path><path d="M3.477 10.896a4 4 0 0 1 .585-.396"></path><path d="M19.938 10.5a4 4 0 0 1 .585.396"></path><path d="M6 18a4 4 0 0 1-1.967-.516"></path><path d="M19.967 17.484A4 4 0 0 1 18 18"></path></svg>
                            <p class="text-xs font-semibold text-foreground uppercase tracking-wide" style="margin: 0;">AI Reasoning Factors</p>
                        </div>
                        <div class="bg-gradient-to-br from-primary/4 to-secondary/4 rounded-xl border border-primary/10 p-4" style="background: linear-gradient(135deg, rgba(13, 133, 230, 0.04), rgba(124, 58, 237, 0.04)); border-color: rgba(13, 133, 230, 0.1);">
                            <ul style="margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 0.625rem;">
                                ${o}
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="p-4 border-t border-border/40 bg-muted/20 text-xs text-muted-foreground text-center" style="border-top: 1px solid rgba(226, 232, 240, 0.4); background-color: rgba(241, 245, 249, 0.2); padding: 1rem; text-align: center;">Generated by John Doe · ${e.date} ${e.time}</div>
            `,typeof lucide<"u"&&lucide.createIcons(),n.classList.add("open"),i.classList.add("open")}function x(){n.classList.remove("open"),i.classList.remove("open")}m.forEach(t=>{t.addEventListener("click",()=>{const e=t.getAttribute("data-id");v(e)})}),i.addEventListener("click",t=>{t.target.closest("#drawer-close-btn")&&x()}),n.addEventListener("click",x)});
