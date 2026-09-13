/** @type {import('next').NextConfig} */
const nextConfig = {
  eslint: {
    // Linting is run separately in CI; don't fail production builds on lint warnings.
    ignoreDuringBuilds: true,
  },
};

export default nextConfig;
