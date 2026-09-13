import Stripe from "stripe";

let _stripe: Stripe | null = null;

/** Lazily-constructed Stripe client so `next build` never needs a real key. */
export function getStripe(): Stripe {
  if (_stripe) return _stripe;
  const key = process.env.STRIPE_SECRET_KEY;
  if (!key) {
    throw new Error("STRIPE_SECRET_KEY is not set. Add it in Vercel's Environment Variables.");
  }
  _stripe = new Stripe(key, {
    apiVersion: "2024-06-20",
  });
  return _stripe;
}

export function priceIdForPlan(plan: "monthly" | "weekly"): string {
  const id = plan === "monthly" ? process.env.STRIPE_PRICE_MONTHLY : process.env.STRIPE_PRICE_WEEKLY;
  if (!id) {
    throw new Error(
      `STRIPE_PRICE_${plan.toUpperCase()} is not set. Create the recurring Price in Stripe and paste its id into Vercel's Environment Variables.`
    );
  }
  return id;
}
